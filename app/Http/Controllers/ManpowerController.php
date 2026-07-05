<?php

namespace App\Http\Controllers;

use App\Models\PjctEmp;
use Illuminate\Http\Request;

class ManpowerController extends Controller
{
    public function index(Request $request)
    {
        $search  = $request->input('search', '');
        $unit    = $request->input('unit', '');
        $jabatan = $request->input('jabatan', '');

        $query = PjctEmp::query();

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

        $employees = $query->orderBy('emp_unit')->orderBy('emp_area')->orderBy('emp_name')->paginate(50)->withQueryString();

        $all = PjctEmp::query();
        $kpi = [
            'total'   => $all->count(),
            'reg2'    => (clone $all)->where('emp_unit', 'Regional 2')->count(),
            'tcop'    => (clone $all)->where('emp_unit', 'Technology Operation')->count(),
            'teknisi' => (clone $all)->where('emp_levname', 'Teknisi')->count(),
        ];

        $filterUnits    = PjctEmp::distinct()->orderBy('emp_unit')->pluck('emp_unit');
        $filterJabatans = PjctEmp::distinct()->orderBy('emp_levname')->pluck('emp_levname');

        return view('project.manpower', compact(
            'employees', 'kpi',
            'filterUnits', 'filterJabatans',
            'search', 'unit', 'jabatan'
        ));
    }
}
