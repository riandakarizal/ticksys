<?php

namespace App\Http\Controllers;

use App\Models\PjctEmp;
use App\Models\PjctMain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ManpowerController extends Controller
{
    public function index(Request $request)
    {
        $search  = $request->input('search', '');
        $unit    = $request->input('unit', '');
        $jabatan = $request->input('jabatan', '');

        // Manpower dibatasi ke project dalam divisi user (sama seperti Asset & Monitoring).
        $keys = $this->allowedProjectKeys($request->user()->allowedDivCodes());
        $scoped = fn (): Builder => PjctEmp::query()
            ->when($keys !== null, fn ($q) => $q->whereIn('emp_pjctid', $keys));

        $query = $scoped();

        if ($unit)    $query->where('emp_unit', $unit);
        if ($jabatan) $query->where('emp_levname', $jabatan);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('emp_name', 'like', "%{$search}%")
                  ->orWhere('emp_id', 'like', "%{$search}%")
                  ->orWhere('emp_div', 'like', "%{$search}%")
                  ->orWhere('emp_area', 'like', "%{$search}%")
                  ->orWhere('emp_coid', 'like', "%{$search}%");
            });
        }

        $employees = $query->orderBy('emp_unit')->orderBy('emp_area')->orderBy('emp_name')->paginate(25)->withQueryString();

        $all = $scoped();
        $kpi = [
            'total'   => $all->count(),
            'reg2'    => (clone $all)->where('emp_unit', 'Regional 2')->count(),
            'tcop'    => (clone $all)->where('emp_unit', 'Technology Operation')->count(),
            'teknisi' => (clone $all)->where('emp_levname', 'Teknisi')->count(),
        ];

        $filterUnits    = $scoped()->distinct()->orderBy('emp_unit')->pluck('emp_unit');
        $filterJabatans = $scoped()->distinct()->orderBy('emp_levname')->pluck('emp_levname');

        return view('project.manpower', compact(
            'employees', 'kpi',
            'filterUnits', 'filterJabatans',
            'search', 'unit', 'jabatan'
        ));
    }

    /**
     * Nilai `emp_pjctid` yang boleh dilihat, atau null kalau tanpa batas (superadmin/vip).
     *
     * Data baru menyimpan ID project (`PJxxxx`); data lama menyimpan nomor kontrak yang
     * terpotong 20 karakter (lebar kolom `emp_pjctid`) — keduanya dicocokkan.
     *
     * @return array<int, string>|null
     */
    private function allowedProjectKeys(?array $allowedDivs): ?array
    {
        if ($allowedDivs === null) {
            return null;
        }

        $projects = PjctMain::withTrashed()->whereIn('pjct_div', $allowedDivs)->get(['id', 'pjct_contract']);

        return $projects->pluck('id')
            ->merge($projects->pluck('pjct_contract')->filter()->map(fn ($c) => mb_substr(trim($c), 0, 20)))
            ->unique()->values()->all();
    }
}
