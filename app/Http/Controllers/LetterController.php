<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\LetterCounter;
use App\Models\LetterType;
use App\Models\PjctMain;
use App\Support\LetterExportService;
use App\Support\LetterNumberService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Surat → Nomor Surat: register nomor surat (superadmin + admin).
 *
 * Nomor hanya dibuat lewat LetterNumberService. Setelah dibuat, jenis/tanggal/nomor tidak bisa
 * diubah — yang bisa diedit hanya data surat (perihal, tujuan, penandatangan, PIC, project, file).
 * Nomor yang salah dibatalkan (dengan alasan), tidak pernah dihapus, supaya tidak ada lubang tanpa jejak.
 */
class LetterController extends Controller
{
    /** Ukuran maksimal file surat (KB) — selaraskan dengan docker/php/uploads.ini. */
    public const MAX_FILE_KB = 5120;

    private const FILE_DIR = 'letter-files';

    private const PER_PAGE = [25, 50, 100];

    private const SORTS = ['date', 'number', 'created'];

    private const MESSAGES = [
        'letter_type_id.required' => 'Pilih jenis surat.',
        'letter_type_id.exists' => 'Jenis surat tidak ditemukan atau sudah dinonaktifkan.',
        'letter_date.required' => 'Isi tanggal surat.',
        'letter_date.after_or_equal' => 'Tanggal surat terlalu lama (paling awal 2024).',
        'letter_date.before_or_equal' => 'Tanggal surat terlalu jauh ke depan.',
        'quantity.max' => 'Maksimal 50 nomor sekali buat.',
        'subject.required' => 'Isi perihal surat.',
        'pjct_id.exists' => 'Project tidak ditemukan.',
        'file.prohibited_unless' => 'File hanya bisa diunggah kalau membuat 1 nomor — untuk banyak nomor, unggah lewat Edit di masing-masing nomor.',
        'file.mimes' => 'File surat harus PDF, JPG, atau PNG.',
        'file.max' => 'Ukuran file maksimal 5 MB.',
        'file.uploaded' => 'File gagal diunggah — kemungkinan ukurannya melebihi 5 MB.',
        'cancel_reason.required' => 'Isi alasan pembatalan.',
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $perPage = in_array((int) $request->input('per_page'), self::PER_PAGE, true) ? (int) $request->input('per_page') : 25;

        $letters = $this->sorted($this->filteredQuery($filters), $filters)
            ->with(['type', 'project', 'creator'])
            ->paginate($perPage)
            ->withQueryString();

        $year = (int) now()->year;
        $createdIds = (array) $request->session()->get('created_letters', []);
        $subNext = $this->nextSubNumbers($letters->getCollection());

        return view('letters.index', [
            'letters' => $letters,
            // Data baris untuk dialog Edit / Turunan / Batal (diisi lewat JS).
            'rows' => $letters->getCollection()->mapWithKeys(fn (Letter $l) => [$l->id => [
                'number' => $l->number,
                'type' => $l->type?->name,
                'code' => $l->code,
                'unit' => $l->unit_code,
                'year' => $l->year,
                'seq' => str_pad((string) $l->seq, 3, '0', STR_PAD_LEFT),
                'date' => $l->letter_date?->toDateString(),
                'subject' => $l->subject,
                'signer' => $l->signer,
                'recipient' => $l->recipient,
                'pic' => $l->pic,
                'pjct_id' => $l->pjct_id,
                'project' => $l->project ? $l->project->id.' — '.$l->project->pjct_name : $l->pjct_id,
                'file_name' => $l->file_name,
                'next_sub' => ($subNext[$l->code.'|'.$l->year.'|'.$l->seq] ?? 0) + 1,
            ]]),
            'filters' => $filters,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE,
            'types' => LetterType::query()->orderBy('name')->get(),
            'years' => Letter::query()->select('year')->distinct()->orderByDesc('year')->pluck('year'),
            'stats' => [
                'year' => $year,
                'year_total' => Letter::query()->where('year', $year)->where('status', Letter::STATUS_ACTIVE)->count(),
                'month_total' => Letter::query()->where('year', $year)->where('month', now()->month)->where('status', Letter::STATUS_ACTIVE)->count(),
                'cancelled' => Letter::query()->where('status', Letter::STATUS_CANCELLED)->count(),
                'flagged' => Letter::query()->whereNotNull('import_note')->count(),
            ],
            'hasImported' => Letter::query()->where('source', 'import')->exists(),
            // Perkiraan nomor berikutnya per tahun → kode, untuk pratinjau di form (nomor pasti diambil saat simpan).
            'nextSeq' => LetterCounter::query()->get()->groupBy('year')
                ->map(fn ($g) => $g->pluck('last_seq', 'code')->map(fn ($s) => $s + 1)),
            'projects' => PjctMain::query()->visibleTo($request->user())->orderBy('pjct_name')->get(['id', 'pjct_name']),
            'suggest' => [
                'signer' => $this->suggestions('signer'),
                'recipient' => $this->suggestions('recipient'),
                'pic' => $this->suggestions('pic'),
            ],
            'created' => $createdIds
                ? Letter::query()->with('type')->whereIn('id', $createdIds)->orderBy('id')->get()
                : collect(),
        ]);
    }

    public function store(Request $request, LetterNumberService $numbers): RedirectResponse
    {
        $data = $request->validate([
            'letter_type_id' => ['required', Rule::exists('letter_types', 'id')->where('is_active', true)],
            ...$this->dateRules(),
            ...$this->detailRules(),
            'quantity' => ['required', 'integer', 'min:1', 'max:'.LetterNumberService::MAX_BATCH],
            'file' => $this->fileRules(true),
        ], self::MESSAGES);

        $letters = $numbers->issue(
            LetterType::query()->findOrFail($data['letter_type_id']),
            CarbonImmutable::parse($data['letter_date']),
            (int) $data['quantity'],
            $this->details($data),
            Auth::id()
        );

        $this->attachFile($request, $letters->first());

        return redirect()->route('letters.index')
            ->with('created_letters', $letters->pluck('id')->all())
            ->with('success', $letters->count() === 1
                ? 'Nomor surat dibuat: '.$letters->first()->number
                : $letters->count().' nomor surat dibuat.');
    }

    /** Nomor turunan (.1, .2, ...) dari nomor yang sudah ada. */
    public function storeSub(Request $request, Letter $letter, LetterNumberService $numbers): RedirectResponse
    {
        $this->ensureActive($letter);

        $data = $request->validate([
            ...$this->dateRules(),
            ...$this->detailRules(),
            'quantity' => ['required', 'integer', 'min:1', 'max:'.LetterNumberService::MAX_BATCH],
            'file' => $this->fileRules(true),
        ], self::MESSAGES);

        $date = CarbonImmutable::parse($data['letter_date']);
        if ((int) $date->year !== $letter->year) {
            throw ValidationException::withMessages([
                'letter_date' => "Tanggal nomor turunan harus di tahun {$letter->year}, sama dengan nomor induknya.",
            ]);
        }

        $letters = $numbers->issueSub($letter, $date, (int) $data['quantity'], $this->details($data), Auth::id());
        $this->attachFile($request, $letters->first());

        return redirect()->route('letters.index')
            ->with('created_letters', $letters->pluck('id')->all())
            ->with('success', $letters->count() === 1
                ? 'Nomor turunan dibuat: '.$letters->first()->number
                : $letters->count().' nomor turunan dibuat.');
    }

    public function update(Request $request, Letter $letter): RedirectResponse
    {
        $this->ensureActive($letter);

        $data = $request->validate([
            ...$this->detailRules(),
            'file' => $this->fileRules(false),
            'remove_file' => ['nullable', 'boolean'],
        ], self::MESSAGES);

        $letter->fill($this->details($data) + ['updated_by' => Auth::id()]);

        if ($request->hasFile('file') || $request->boolean('remove_file')) {
            $this->deleteFile($letter);
        }
        $letter->save();
        $this->attachFile($request, $letter);

        return back()->with('success', 'Data surat '.$letter->number.' disimpan.');
    }

    public function cancel(Request $request, Letter $letter): RedirectResponse
    {
        $this->ensureActive($letter);

        $data = $request->validate(['cancel_reason' => ['required', 'string', 'max:255']], self::MESSAGES);

        $letter->update([
            'status' => Letter::STATUS_CANCELLED,
            'cancel_reason' => $data['cancel_reason'],
            'cancelled_by' => Auth::id(),
            'cancelled_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', 'Nomor '.$letter->number.' dibatalkan.');
    }

    public function file(Letter $letter)
    {
        abort_unless($letter->file_path && Storage::exists($letter->file_path), 404);

        return Storage::response($letter->file_path, $letter->file_name, [], 'inline');
    }

    public function export(Request $request, LetterExportService $exporter): StreamedResponse
    {
        $filters = $this->filters($request);
        $query = $this->filteredQuery($filters);

        // Rekap dihitung lebih dulu: cursor() di bawah membaca secara unbuffered di MySQL,
        // jadi query lain tidak boleh berjalan selagi baris dibaca.
        $total = (clone $query)->count();
        $typeNames = LetterType::query()->pluck('name', 'id');
        $breakdowns = [
            'Per Jenis Surat' => $this->breakdown($query, 'letter_type_id')
                ->mapWithKeys(fn ($n, $id) => [$typeNames[$id] ?? $id => $n]),
            'Per Tahun' => $this->breakdown($query, 'year'),
            'Per Status' => $this->breakdown($query, 'status')
                ->mapWithKeys(fn ($n, $s) => [$s === Letter::STATUS_CANCELLED ? 'Batal' : 'Aktif' => $n]),
            'Per PIC' => $this->breakdown($query, 'pic', 30),
        ];

        $spreadsheet = $exporter->make(
            $this->sorted(clone $query, $filters)->with(['type', 'project', 'creator'])->cursor(),
            $this->filterLabels($filters, $typeNames),
            $breakdowns,
            $total,
            Auth::user()?->user_name ?? '-'
        );

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'register-nomor-surat-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ─── Validasi & data ──────────────────────────────────────────────────

    /** @return array<string, array<int, mixed>> */
    private function dateRules(): array
    {
        return [
            'letter_date' => ['required', 'date', 'after_or_equal:2024-01-01', 'before_or_equal:'.now()->addYear()->endOfYear()->toDateString()],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function detailRules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:500'],
            'signer' => ['nullable', 'string', 'max:255'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'pic' => ['nullable', 'string', 'max:100'],
            'pjct_id' => ['nullable', 'string', Rule::exists('pjct_main', 'id')],
        ];
    }

    /** @return array<int, string> */
    private function fileRules(bool $singleOnly): array
    {
        return array_values(array_filter([
            'nullable',
            $singleOnly ? 'prohibited_unless:quantity,1' : null,
            'file',
            'mimes:pdf,jpg,jpeg,png',
            'max:'.self::MAX_FILE_KB,
        ]));
    }

    /** @return array<string, string|null> */
    private function details(array $data): array
    {
        $clean = fn (?string $v) => ($v = trim((string) $v)) === '' ? null : $v;

        return [
            'subject' => $clean($data['subject'] ?? null),
            'signer' => $clean($data['signer'] ?? null),
            'recipient' => $clean($data['recipient'] ?? null),
            'pic' => $clean($data['pic'] ?? null),
            'pjct_id' => $clean($data['pjct_id'] ?? null),
        ];
    }

    private function ensureActive(Letter $letter): void
    {
        if ($letter->isCancelled()) {
            throw ValidationException::withMessages(['letter' => 'Nomor '.$letter->number.' sudah dibatalkan.']);
        }
    }

    private function attachFile(Request $request, ?Letter $letter): void
    {
        $file = $request->file('file');
        if (! $letter || ! $file) {
            return;
        }

        $letter->update([
            'file_path' => $file->store(self::FILE_DIR.'/'.$letter->year),
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
        ]);
    }

    private function deleteFile(Letter $letter): void
    {
        if ($letter->file_path) {
            Storage::delete($letter->file_path);
        }

        $letter->fill(['file_path' => null, 'file_name' => null, 'file_size' => null]);
    }

    /**
     * Turunan tertinggi yang sudah ada untuk tiap nomor induk di halaman ini, kunci `kode|tahun|urut`.
     *
     * @param  Collection<int, Letter>  $letters
     * @return Collection<string, int>
     */
    private function nextSubNumbers(Collection $letters): Collection
    {
        $bases = $letters->unique(fn (Letter $l) => $l->code.'|'.$l->year.'|'.$l->seq);
        if ($bases->isEmpty()) {
            return collect();
        }

        return Letter::query()
            ->selectRaw('code, year, seq, MAX(sub_seq) as max_sub')
            ->where(function (Builder $q) use ($bases): void {
                foreach ($bases as $l) {
                    $q->orWhere(fn (Builder $w) => $w->where('code', $l->code)->where('year', $l->year)->where('seq', $l->seq));
                }
            })
            ->groupBy('code', 'year', 'seq')
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($r) => [$r->code.'|'.$r->year.'|'.$r->seq => (int) $r->max_sub]);
    }

    /** Isian yang paling sering dipakai, untuk saran (datalist) di form. */
    private function suggestions(string $column): Collection
    {
        return Letter::query()
            ->select($column)
            ->whereNotNull($column)
            ->groupBy($column)
            ->orderByRaw('count(*) desc')
            ->limit(150)
            ->pluck($column);
    }

    // ─── Filter & sort ────────────────────────────────────────────────────

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        $sort = (string) $request->input('sort', 'date');
        $status = (string) $request->input('status', '');
        $source = (string) $request->input('source', '');

        return [
            'search' => trim((string) $request->input('search', '')),
            'type' => (string) $request->input('type', ''),
            'year' => (string) $request->input('year', ''),
            'month' => (string) $request->input('month', ''),
            'status' => in_array($status, [Letter::STATUS_ACTIVE, Letter::STATUS_CANCELLED], true) ? $status : '',
            'source' => in_array($source, ['app', 'import'], true) ? $source : '',
            'flagged' => $request->boolean('flagged') ? '1' : '',
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'date',
            'dir' => strtolower((string) $request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }

    /** @param  array<string, string>  $filters */
    private function filteredQuery(array $filters): Builder
    {
        $query = Letter::query();

        if ($filters['type'] !== '') {
            $query->where('letter_type_id', (int) $filters['type']);
        }
        if ($filters['year'] !== '') {
            $query->where('year', (int) $filters['year']);
        }
        if ($filters['month'] !== '') {
            $query->where('month', (int) $filters['month']);
        }
        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if ($filters['source'] !== '') {
            $query->where('source', $filters['source']);
        }
        if ($filters['flagged'] !== '') {
            $query->whereNotNull('import_note');
        }
        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($term): void {
                foreach (['number', 'subject', 'signer', 'recipient', 'pic', 'pjct_id'] as $column) {
                    $q->orWhere($column, 'like', $term);
                }
            });
        }

        return $query;
    }

    /** @param  array<string, string>  $filters */
    private function sorted(Builder $query, array $filters): Builder
    {
        $dir = $filters['dir'];
        $columns = match ($filters['sort']) {
            'number' => ['code', 'year', 'seq', 'sub_seq', 'id'],
            'created' => ['created_at', 'id'],
            default => ['letter_date', 'year', 'seq', 'sub_seq', 'id'],
        };

        foreach ($columns as $column) {
            $query->orderBy($column, $dir);
        }

        return $query;
    }

    private function breakdown(Builder $query, string $column, ?int $limit = null): Collection
    {
        $rows = (clone $query)->reorder()
            ->selectRaw("{$column} as label, COUNT(*) as total")
            ->groupBy($column)
            ->orderByDesc('total');

        if ($limit) {
            $rows->limit($limit);
        }

        return $rows->toBase()->get()->mapWithKeys(fn ($r) => [(string) $r->label => (int) $r->total]);
    }

    /** @return array<int, string> */
    private function filterLabels(array $filters, Collection $typeNames): array
    {
        $labels = [];
        $map = [
            'search' => ['Search', fn ($v) => $v],
            'type' => ['Jenis', fn ($v) => $typeNames[(int) $v] ?? $v],
            'year' => ['Tahun', fn ($v) => $v],
            'month' => ['Bulan', fn ($v) => Letter::ROMAN_MONTHS[(int) $v] ?? $v],
            'status' => ['Status', fn ($v) => $v === Letter::STATUS_CANCELLED ? 'Batal' : 'Aktif'],
            'source' => ['Sumber', fn ($v) => $v === 'import' ? 'Impor Excel' : 'PRISM'],
            'flagged' => ['Data impor bertanda', fn () => 'Ya'],
        ];

        foreach ($map as $key => [$label, $format]) {
            if (($filters[$key] ?? '') !== '') {
                $labels[] = $label.': '.$format($filters[$key]);
            }
        }

        return $labels;
    }
}
