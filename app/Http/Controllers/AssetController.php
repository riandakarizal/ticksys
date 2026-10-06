<?php

namespace App\Http\Controllers;

use App\Models\AstMain;
use App\Models\PjctMain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $search   = $request->input('search', '');
        $type     = $request->input('type', '');
        $stat     = $request->input('stat', '');
        $cond     = $request->input('cond', '');
        $loc      = $request->input('loc', '');

        // Data aset dibatasi ke project dalam divisi user (sama seperti Monitoring). Daftar
        // ID project diambil lebih dulu: `ast_main` dan `pjct_main` beda collation, jadi
        // JOIN/subquery SQL antar keduanya ditolak MySQL.
        $allowedDivs = $request->user()->allowedDivCodes();
        $projectIds = $allowedDivs === null
            ? null
            : PjctMain::withTrashed()->whereIn('pjct_div', $allowedDivs)->pluck('id')->all();
        $scoped = fn (): Builder => AstMain::query()
            ->when($projectIds !== null, fn ($q) => $q->whereIn('ast_pjctid', $projectIds));

        $query = $scoped();

        if ($type) $query->where('ast_type', $type);
        if ($stat) $query->where('ast_stat', $stat);
        if ($cond) $query->where('ast_cond', $cond);
        if ($loc)  $query->where('ast_userloc', $loc);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('ast_brand', 'like', "%{$search}%")
                  ->orWhere('ast_brandmodel', 'like', "%{$search}%")
                  ->orWhere('ast_serial', 'like', "%{$search}%")
                  ->orWhere('ast_username', 'like', "%{$search}%")
                  ->orWhere('ast_pjctid', 'like', "%{$search}%");
            });
        }

        $assets = $query->orderBy('id')->paginate(25)->withQueryString();

        $all   = $scoped();
        $kpi   = [
            'total'     => $all->count(),
            'aktif'     => (clone $all)->where('ast_stat', 'Aktif')->count(),
            'sewa'      => (clone $all)->whereIn('ast_stat', ['Aktif-Sewa', 'Aktif-SewaBeli'])->count(),
            'backup'    => (clone $all)->where('ast_stat', 'Back Up')->count(),
            'nonaktif'  => (clone $all)->where('ast_stat', 'Non-Aktif')->count(),
            'aktif_end' => $scoped()->where('ast_stat', 'Aktif')
                ->whereHas('project', fn($q) => $q->where('pjct_status', 'END'))
                ->count(),
        ];

        $types = $scoped()->selectRaw('ast_type, COUNT(*) as c')
            ->groupBy('ast_type')->orderByDesc('c')->pluck('c', 'ast_type');

        $filterTypes = $scoped()->distinct()->orderBy('ast_type')->pluck('ast_type');
        $filterStats = $scoped()->distinct()->orderBy('ast_stat')->pluck('ast_stat');
        $filterConds = ['Excellence', 'Good', 'Fair', 'Bad'];
        $filterLocs     = $scoped()->selectRaw('ast_userloc, COUNT(*) as c')
            ->groupBy('ast_userloc')->orderByDesc('c')->limit(30)->pluck('ast_userloc');

        $projectNames = PjctMain::orderBy('id')->pluck('pjct_name', 'id');

        return view('project.assets', compact(
            'assets', 'kpi', 'types',
            'filterTypes', 'filterStats', 'filterConds', 'filterLocs',
            'search', 'type', 'stat', 'cond', 'loc', 'projectNames'
        ));
    }
}
