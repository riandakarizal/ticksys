<?php

namespace App\Http\Controllers;

use App\Models\EqtChangeLog;
use App\Models\PjctMain;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function index(Request $request)
    {
        $yearFilter   = $request->input('year', 'all');
        $statusFilter = $request->input('status', 'all');
        $typeFilter   = $request->input('type', 'all');
        $unitFilter   = $request->input('unit', 'all');
        $search       = $request->input('search', '');
        $showArchived = $request->boolean('archived', false);

        $user        = auth()->user();
        $allowedDivs = $user->allowedDivCodes();

        $query = $showArchived ? PjctMain::withTrashed() : PjctMain::query();
        if ($allowedDivs !== null) {
            $query->whereIn('pjct_div', $allowedDivs);
        }

        if ($yearFilter !== 'all') {
            $query->whereYear('pjct_codate', $yearFilter);
        }
        if ($statusFilter !== 'all') {
            $query->where('pjct_status', $statusFilter);
        }
        if ($typeFilter !== 'all') {
            $query->where('pjct_type', $typeFilter);
        }
        if ($unitFilter !== 'all') {
            $query->where('pjct_div', 'like', $unitFilter.'%');
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('pjct_name', 'like', "%{$search}%")
                  ->orWhere('pjct_client', 'like', "%{$search}%")
                  ->orWhere('pjct_area', 'like', "%{$search}%")
                  ->orWhere('pjct_contract', 'like', "%{$search}%");
            });
        }

        $projects = $query->withCount('assets')
            ->with(['docs:id,doc_pjctid,doc_type'])
            ->orderByRaw('pjct_codate DESC')->orderBy('id')->paginate(25)->withQueryString();

        $kpiQuery = PjctMain::query();
        if ($allowedDivs !== null) {
            $kpiQuery->whereIn('pjct_div', $allowedDivs);
        }
        $allForKpi = $kpiQuery->get();
        $kpi = [
            'total'  => $allForKpi->count(),
            'upc'    => $allForKpi->where('pjct_status', 'UPC')->count(),
            'og'     => $allForKpi->where('pjct_status', 'OG')->count(),
            'hvr'    => $allForKpi->where('pjct_status', 'HVR')->count(),
            'dly'    => $allForKpi->where('pjct_status', 'DLY')->count(),
            'end'    => $allForKpi->where('pjct_status', 'END')->count(),
            'rent'   => $allForKpi->where('pjct_type', 'RENT')->count(),
            'supply' => $allForKpi->where('pjct_type', 'SUPPLY')->count(),
            'jasa'   => $allForKpi->where('pjct_type', 'JASA')->count(),
            'nilai'  => $allForKpi->sum('pjct_value'),
        ];

        $isAdmin    = $user?->isSuperAdmin() || $user?->isAdmin();
        $changeLogs = collect();

        return view('monitoring.index', compact(
            'projects', 'kpi',
            'yearFilter', 'statusFilter', 'typeFilter', 'unitFilter', 'search', 'showArchived',
            'isAdmin', 'changeLogs'
        ));
    }

    public function store(Request $request, string $type)
    {
        abort_unless($type === 'projects', 404);
        $record = PjctMain::create($this->projectData($request));
        return back()->with('success', 'Project added successfully.');
    }

    public function update(Request $request, string $type, int $id)
    {
        abort_unless($type === 'projects', 404);
        $record = PjctMain::findOrFail($id);
        $record->update($this->projectData($request));
        return back()->with('success', 'Project updated successfully.');
    }

    public function destroy(string $type, int $id)
    {
        abort_unless($type === 'projects', 404);
        PjctMain::findOrFail($id)->delete();
        return back()->with('success', 'Project archived.');
    }

    public function restore(string $type, int $id)
    {
        abort_unless($type === 'projects', 404);
        PjctMain::withTrashed()->findOrFail($id)->restore();
        return back()->with('success', 'Project restored.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function projectData(Request $request): array
    {
        return $request->validate([
            'pjct_contract'    => 'nullable|string|max:255',
            'pjct_codate'      => 'nullable|date',
            'pjct_div'         => 'nullable|string|max:225',
            'pjct_name'        => 'required|string|max:255',
            'pjct_type'        => 'required|in:RENT,SUPPLY,JASA',
            'pjct_client'      => 'nullable|string|max:255',
            'pjct_area'        => 'nullable|string|max:255',
            'pjct_value'       => 'nullable|integer|min:0',
            'pjct_costart'     => 'nullable|date',
            'pjct_totalperiod' => 'nullable|integer|min:0',
            'pjct_coend_m'     => 'nullable|date',
            'pjct_status'      => 'required|in:UPC,OG,HVR,DLY,END',
            'pjct_misc'        => 'nullable|string|max:1000',
        ]);
    }
}
