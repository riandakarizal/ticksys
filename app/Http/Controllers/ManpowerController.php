<?php

namespace App\Http\Controllers;

use App\Models\PjctEmp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ManpowerController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $unit = $request->input('unit', '');
        $jabatan = $request->input('jabatan', '');

        // Manpower dibatasi ke project yang boleh dilihat user (sama seperti Asset & Monitoring).
        // `emp_pjctid` = ID project (`PJxxxx`); null = tanpa batas (superadmin/vip).
        $projectIds = $request->user()->visibleProjectIds();
        $scoped = fn (): Builder => PjctEmp::query()
            ->when($projectIds !== null, fn ($q) => $q->whereIn('emp_pjctid', $projectIds));

        $query = $scoped();

        if ($unit) {
            $query->where('emp_unit', $unit);
        }
        if ($jabatan) {
            $query->where('emp_levname', $jabatan);
        }
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
            'total' => $all->count(),
            'reg2' => (clone $all)->where('emp_unit', 'Regional 2')->count(),
            'tcop' => (clone $all)->where('emp_unit', 'Technology Operation')->count(),
            'teknisi' => (clone $all)->where('emp_levname', 'Teknisi')->count(),
        ];

        $filterUnits = $scoped()->distinct()->orderBy('emp_unit')->pluck('emp_unit');
        $filterJabatans = $scoped()->distinct()->orderBy('emp_levname')->pluck('emp_levname');

        return view('project.manpower', compact(
            'employees', 'kpi',
            'filterUnits', 'filterJabatans',
            'search', 'unit', 'jabatan'
        ));
    }
}
