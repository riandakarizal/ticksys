<?php

namespace App\Http\Controllers;

use App\Models\AstMain;
use App\Models\PjctMain;
use App\Support\AssetReportExportService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report → Data: tarikan mentah seluruh kolom `ast_main` dengan filter lengkap.
 *
 * Berbeda dari `AssetController` (halaman Project → Asset) yang hanya menampilkan
 * kolom ringkas untuk operasional harian; di sini semua kolom ikut ditarik dan
 * bisa diekspor ke Excel lengkap dengan kop laporan dan sheet ringkasan.
 */
class AssetReportController extends Controller
{
    /** Kolom yang boleh dipakai untuk sorting — sisanya diabaikan. */
    private const SORTABLE = [
        'id', 'ast_type', 'ast_brand', 'ast_brandmodel', 'ast_prodyear', 'ast_serial',
        'ast_vendid', 'ast_username', 'ast_userreg', 'ast_userloc', 'ast_userlocdet',
        'ast_cond', 'ast_delvdate', 'ast_purcdate', 'ast_stat', 'ast_pjctid', 'ast_docid',
    ];

    /** Pilihan jumlah baris per halaman. */
    private const PER_PAGE = [25, 50, 100, 200];

    /** Label filter untuk dicetak di kop laporan Excel. */
    private const FILTER_LABELS = [
        'search' => 'Search',
        'type' => 'Type',
        'brand' => 'Brand',
        'stat' => 'Status',
        'cond' => 'Kondisi',
        'reg' => 'Region',
        'loc' => 'Lokasi',
        'project' => 'Project',
        'year' => 'Tahun',
        'delv_from' => 'Tgl Kirim dari',
        'delv_to' => 'Tgl Kirim s/d',
        'purc_from' => 'Tgl Beli dari',
        'purc_to' => 'Tgl Beli s/d',
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $query = $this->filteredQuery($filters);

        $perPage = in_array((int) $request->input('per_page'), self::PER_PAGE, true)
            ? (int) $request->input('per_page')
            : 25;

        $assets = (clone $query)
            ->orderBy($filters['sort'], $filters['dir'])
            ->paginate($perPage)
            ->withQueryString();

        $summary = [
            'filtered' => $assets->total(),
            'total' => AstMain::query()->count(),
            'types' => (clone $query)->distinct()->count('ast_type'),
            'locations' => (clone $query)->distinct()->count('ast_userloc'),
            'projects' => (clone $query)->whereNotNull('ast_pjctid')->distinct()->count('ast_pjctid'),
        ];

        return view('report.data', [
            'assets' => $assets,
            'summary' => $summary,
            'filters' => $filters,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE,
            'options' => $this->filterOptions(),
            'projectNames' => $this->projectNames(),
        ]);
    }

    public function export(Request $request, AssetReportExportService $exporter): StreamedResponse
    {
        $filters = $this->filters($request);
        $query = $this->filteredQuery($filters);
        $projectNames = $this->projectNames();

        // Rekap dihitung lebih dulu: cursor() di bawah memakai koneksi secara unbuffered,
        // jadi query lain tidak boleh berjalan selagi baris dibaca.
        $total = (clone $query)->count();
        $breakdowns = [
            'Per Status' => $this->breakdown($query, 'ast_stat'),
            'Per Kondisi' => $this->breakdown($query, 'ast_cond'),
            'Per Type' => $this->breakdown($query, 'ast_type', 25),
            'Per Region' => $this->breakdown($query, 'ast_userreg', 25),
        ];

        $spreadsheet = $exporter->make(
            (clone $query)->orderBy($filters['sort'], $filters['dir'])->cursor(),
            $this->filterLabels($filters),
            $breakdowns,
            $projectNames,
            $total,
            Auth::user()?->user_name ?? '-'
        );

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'laporan-data-aset-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Rekap jumlah baris per nilai satu kolom, untuk sheet Ringkasan.
     *
     * @return Collection<string, int>
     */
    private function breakdown(Builder $query, string $column, ?int $limit = null): Collection
    {
        $rows = (clone $query)
            ->selectRaw("{$column} as label, COUNT(*) as total")
            ->groupBy($column)
            ->orderByDesc('total');

        if ($limit) {
            $rows->limit($limit);
        }

        return $rows->get()->pluck('total', 'label');
    }

    /**
     * Filter aktif dalam bentuk teks, mis. `Status: Aktif`.
     *
     * @return array<int, string>
     */
    private function filterLabels(array $filters): array
    {
        $projectNames = null;
        $labels = [];

        foreach (self::FILTER_LABELS as $key => $label) {
            if (($filters[$key] ?? '') === '') {
                continue;
            }

            $value = $filters[$key];

            if ($key === 'project') {
                $projectNames ??= $this->projectNames();
                $value = $value === '__none'
                    ? 'Tanpa project'
                    : $value.' — '.($projectNames[$value] ?? '?');
            }

            $labels[] = $label.': '.$value;
        }

        return $labels;
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        $sort = (string) $request->input('sort', 'id');
        $dir = strtolower((string) $request->input('dir', 'asc'));

        return [
            'search' => trim((string) $request->input('search', '')),
            'type' => (string) $request->input('type', ''),
            'brand' => (string) $request->input('brand', ''),
            'stat' => (string) $request->input('stat', ''),
            'cond' => (string) $request->input('cond', ''),
            'reg' => (string) $request->input('reg', ''),
            'loc' => (string) $request->input('loc', ''),
            'project' => (string) $request->input('project', ''),
            'year' => (string) $request->input('year', ''),
            'delv_from' => (string) $request->input('delv_from', ''),
            'delv_to' => (string) $request->input('delv_to', ''),
            'purc_from' => (string) $request->input('purc_from', ''),
            'purc_to' => (string) $request->input('purc_to', ''),
            'sort' => in_array($sort, self::SORTABLE, true) ? $sort : 'id',
            'dir' => $dir === 'desc' ? 'desc' : 'asc',
        ];
    }

    /** @param  array<string, string>  $filters */
    private function filteredQuery(array $filters): Builder
    {
        $query = AstMain::query();

        $exact = [
            'type' => 'ast_type',
            'brand' => 'ast_brand',
            'stat' => 'ast_stat',
            'cond' => 'ast_cond',
            'reg' => 'ast_userreg',
            'loc' => 'ast_userloc',
            'year' => 'ast_prodyear',
        ];

        foreach ($exact as $key => $column) {
            if ($filters[$key] !== '') {
                $query->where($column, $filters[$key]);
            }
        }

        if ($filters['project'] !== '') {
            $filters['project'] === '__none'
                ? $query->where(fn (Builder $q) => $q->whereNull('ast_pjctid')->orWhere('ast_pjctid', ''))
                : $query->where('ast_pjctid', $filters['project']);
        }

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($term): void {
                // Kolom yang punya dropdown sendiri tetap ikut dicari — user sering
                // mengetik "laptop" atau nama lokasi langsung di kotak search.
                foreach (['id', 'ast_type', 'ast_brand', 'ast_brandmodel', 'ast_serial', 'ast_username',
                    'ast_userreg', 'ast_userloc', 'ast_userlocdet', 'ast_pjctid', 'ast_docid',
                    'ast_vendid', 'ast_stat', 'ast_misc'] as $column) {
                    $q->orWhere($column, 'like', $term);
                }
            });
        }

        $ranges = [
            'ast_delvdate' => ['delv_from', 'delv_to'],
            'ast_purcdate' => ['purc_from', 'purc_to'],
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

    /** @return array<string, Collection<int, string>|array<int, string>> */
    private function filterOptions(): array
    {
        return [
            'types' => $this->distinctValues('ast_type'),
            'brands' => $this->distinctValues('ast_brand'),
            'stats' => $this->distinctValues('ast_stat'),
            'conds' => ['Excellence', 'Good', 'Fair', 'Bad'],
            'regs' => $this->distinctValues('ast_userreg'),
            'locs' => $this->distinctValues('ast_userloc'),
            'years' => $this->distinctValues('ast_prodyear'),
        ];
    }

    /** Nilai unik satu kolom, tanpa yang kosong, untuk isi dropdown filter. */
    private function distinctValues(string $column): Collection
    {
        return AstMain::query()
            ->select($column)
            ->distinct()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->orderBy($column)
            ->pluck($column);
    }

    /** @return Collection<string, string> */
    private function projectNames(): Collection
    {
        return PjctMain::query()->orderBy('id')->pluck('pjct_name', 'id');
    }
}
