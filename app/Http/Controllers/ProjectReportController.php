<?php

namespace App\Http\Controllers;

use App\Models\PjctMain;
use App\Support\ProjectImportService;
use App\Support\ProjectReportExportService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report → Projects: tarikan seluruh kolom `pjct_main` dengan filter lengkap + export Excel.
 *
 * Kembaran `AssetReportController`, dengan dua perbedaan: data dibatasi
 * `allowedDivCodes()` (sama seperti halaman Monitoring), dan ada filter kelengkapan
 * untuk menemukan project yang nilai/tanggal/nomor kontraknya masih kosong.
 */
class ProjectReportController extends Controller
{
    /** Kolom yang boleh dipakai untuk sorting — sisanya diabaikan. */
    private const SORTABLE = [
        'id', 'pjct_contract', 'pjct_codate', 'pjct_div', 'pjct_name', 'pjct_type', 'pjct_client',
        'pjct_area', 'pjct_value', 'pjct_costart', 'pjct_totalperiod', 'pjct_coend_m', 'pjct_status',
    ];

    /** Pilihan jumlah baris per halaman. */
    private const PER_PAGE = [25, 50, 100, 200];

    /** Pilihan filter kelengkapan data. */
    public const GAPS = [
        'any' => 'Ada data kosong',
        'value' => 'Tanpa nilai kontrak',
        'date' => 'Tanpa tanggal mulai/selesai',
        'contract' => 'Tanpa nomor kontrak',
    ];

    /** Label filter untuk dicetak di kop laporan Excel. */
    private const FILTER_LABELS = [
        'search' => 'Search',
        'div' => 'Divisi',
        'stat' => 'Status',
        'type' => 'Type',
        'client' => 'Client',
        'area' => 'Area',
        'year' => 'Tahun Kontrak',
        'gap' => 'Kelengkapan',
        'start_from' => 'Mulai dari',
        'start_to' => 'Mulai s/d',
        'end_from' => 'Selesai dari',
        'end_to' => 'Selesai s/d',
        'archived' => 'Arsip',
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $query = $this->filteredQuery($filters);

        $perPage = in_array((int) $request->input('per_page'), self::PER_PAGE, true)
            ? (int) $request->input('per_page')
            : 25;

        $projects = (clone $query)
            ->withCount('assets')
            ->orderBy($filters['sort'], $filters['dir'])
            ->paginate($perPage)
            ->withQueryString();

        $summary = [
            'total' => $this->scopedQuery()->count(),
        ];

        return view('report.projects', [
            'projects' => $projects,
            'summary' => $summary,
            'filters' => $filters,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE,
            'options' => $this->filterOptions(),
        ]);
    }

    public function export(Request $request, ProjectReportExportService $exporter): StreamedResponse
    {
        $filters = $this->filters($request);
        $query = $this->filteredQuery($filters);

        // Rekap dihitung lebih dulu: cursor() di bawah memakai koneksi secara unbuffered,
        // jadi query lain tidak boleh berjalan selagi baris dibaca.
        $total = (clone $query)->count();
        $totalValue = (int) (clone $query)->sum('pjct_value');
        $statusLabels = $this->statusLabels();
        $breakdowns = [
            'Per Status' => $this->breakdown($query, 'pjct_status')
                ->mapWithKeys(fn ($v, $k) => [($statusLabels[$k] ?? $k) => $v]),
            'Per Divisi' => $this->breakdown($query, 'pjct_div'),
            'Per Type' => $this->breakdown($query, 'pjct_type'),
            'Per Client' => $this->breakdown($query, 'pjct_client', 25),
        ];
        $gaps = collect(self::GAPS)->except('any')
            ->mapWithKeys(fn ($label, $gap) => [$label => $this->applyGap(clone $query, $gap)->count()])
            ->all();

        $spreadsheet = $exporter->make(
            (clone $query)->withCount('assets')->orderBy($filters['sort'], $filters['dir'])->cursor(),
            $this->filterLabels($filters),
            $breakdowns,
            $gaps,
            $total,
            $totalValue,
            Auth::user()?->user_name ?? '-'
        );

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'laporan-data-project-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Rekap jumlah project & total nilai per nilai satu kolom, untuk sheet Ringkasan.
     *
     * @return Collection<string, array{count: int, value: int}>
     */
    private function breakdown(Builder $query, string $column, ?int $limit = null): Collection
    {
        $rows = (clone $query)
            ->selectRaw("{$column} as label, COUNT(*) as total, SUM(pjct_value) as nilai")
            ->groupBy($column)
            ->orderByDesc('total');

        if ($limit) {
            $rows->limit($limit);
        }

        return $rows->toBase()->get()->mapWithKeys(fn ($r) => [
            (string) $r->label => ['count' => (int) $r->total, 'value' => (int) $r->nilai],
        ]);
    }

    /**
     * Filter aktif dalam bentuk teks, mis. `Status: On Going`.
     *
     * @return array<int, string>
     */
    private function filterLabels(array $filters): array
    {
        $labels = [];

        foreach (self::FILTER_LABELS as $key => $label) {
            $value = $filters[$key] ?? '';
            if ($value === '') {
                continue;
            }

            $value = match ($key) {
                'stat' => $this->statusLabels()[$value] ?? $value,
                'gap' => self::GAPS[$value] ?? $value,
                'archived' => $value === 'only' ? 'Hanya yang diarsipkan' : 'Termasuk yang diarsipkan',
                default => $value,
            };

            $labels[] = $label.': '.$value;
        }

        return $labels;
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        $sort = (string) $request->input('sort', 'id');
        $dir = strtolower((string) $request->input('dir', 'asc'));
        $gap = (string) $request->input('gap', '');
        $archived = (string) $request->input('archived', '');

        return [
            'search' => trim((string) $request->input('search', '')),
            'div' => (string) $request->input('div', ''),
            'stat' => (string) $request->input('stat', ''),
            'type' => (string) $request->input('type', ''),
            'client' => (string) $request->input('client', ''),
            'area' => (string) $request->input('area', ''),
            'year' => (string) $request->input('year', ''),
            'gap' => array_key_exists($gap, self::GAPS) ? $gap : '',
            'start_from' => (string) $request->input('start_from', ''),
            'start_to' => (string) $request->input('start_to', ''),
            'end_from' => (string) $request->input('end_from', ''),
            'end_to' => (string) $request->input('end_to', ''),
            'archived' => in_array($archived, ['with', 'only'], true) ? $archived : '',
            'sort' => in_array($sort, self::SORTABLE, true) ? $sort : 'id',
            'dir' => $dir === 'desc' ? 'desc' : 'asc',
        ];
    }

    /**
     * Project yang boleh dilihat user ini — dibatasi divisi seperti halaman Monitoring.
     */
    private function scopedQuery(string $archived = ''): Builder
    {
        $query = match ($archived) {
            'with' => PjctMain::withTrashed(),
            'only' => PjctMain::onlyTrashed(),
            default => PjctMain::query(),
        };

        $allowedDivs = Auth::user()?->allowedDivCodes();
        if ($allowedDivs !== null) {
            $query->whereIn('pjct_div', $allowedDivs);
        }

        return $query;
    }

    /** @param  array<string, string>  $filters */
    private function filteredQuery(array $filters): Builder
    {
        $query = $this->scopedQuery($filters['archived']);

        $exact = [
            'div' => 'pjct_div',
            'stat' => 'pjct_status',
            'type' => 'pjct_type',
            'client' => 'pjct_client',
            'area' => 'pjct_area',
        ];

        foreach ($exact as $key => $column) {
            if ($filters[$key] !== '') {
                $query->where($column, $filters[$key]);
            }
        }

        if ($filters['year'] !== '') {
            $query->whereYear('pjct_codate', $filters['year']);
        }

        if ($filters['gap'] !== '') {
            $this->applyGap($query, $filters['gap']);
        }

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($term): void {
                foreach (['id', 'pjct_contract', 'pjct_name', 'pjct_client', 'pjct_area', 'pjct_div', 'pjct_misc'] as $column) {
                    $q->orWhere($column, 'like', $term);
                }
            });
        }

        $ranges = [
            'pjct_costart' => ['start_from', 'start_to'],
            'pjct_coend_m' => ['end_from', 'end_to'],
        ];

        foreach ($ranges as $column => [$from, $to]) {
            if ($filters[$from] !== '') {
                $query->whereDate($column, '>=', $filters[$from]);
            }

            if ($filters[$to] !== '') {
                $query->whereDate($column, '<=', $filters[$to]);
            }
        }

        return $query;
    }

    /** Batasi ke project yang datanya belum lengkap. */
    private function applyGap(Builder $query, string $gap): Builder
    {
        $noValue = fn (Builder $q) => $q->whereNull('pjct_value')->orWhere('pjct_value', 0);
        $noDate = fn (Builder $q) => $q->whereNull('pjct_costart')->orWhereNull('pjct_coend_m');
        $noContract = fn (Builder $q) => $q->whereNull('pjct_contract')->orWhere('pjct_contract', '');

        return match ($gap) {
            'value' => $query->where($noValue),
            'date' => $query->where($noDate),
            'contract' => $query->where($noContract),
            'any' => $query->where(fn (Builder $q) => $q->where($noValue)->orWhere($noDate)->orWhere($noContract)),
            default => $query,
        };
    }

    /** @return array<string, mixed> */
    private function filterOptions(): array
    {
        $years = $this->scopedQuery()->whereNotNull('pjct_codate')->pluck('pjct_codate')
            ->map(fn ($d) => $d->format('Y'))->unique()->sortDesc()->values();

        return [
            'divs' => $this->distinctValues('pjct_div'),
            'stats' => $this->statusLabels(),
            'types' => ProjectImportService::TYPES,
            'clients' => $this->distinctValues('pjct_client'),
            'areas' => $this->distinctValues('pjct_area'),
            'years' => $years,
            'gaps' => self::GAPS,
        ];
    }

    /** Nilai unik satu kolom (dalam divisi yang boleh dilihat), tanpa yang kosong. */
    private function distinctValues(string $column): Collection
    {
        return $this->scopedQuery()
            ->select($column)
            ->distinct()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->orderBy($column)
            ->pluck($column);
    }

    /** @return array<string, string> kode status → label, mis. `OG` → `On Going` */
    private function statusLabels(): array
    {
        return collect(ProjectImportService::STATUSES)
            ->mapWithKeys(fn ($s) => [$s => (new PjctMain(['pjct_status' => $s]))->statusLabel()])
            ->all();
    }
}
