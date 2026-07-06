<?php

namespace App\Http\Controllers;

use App\Models\EqtChangeLog;
use App\Models\PjctMain;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MonitoringController extends Controller
{
    public function index(Request $request)
    {
        $yearFilter   = $request->input('year', 'all');
        $statusFilter = $request->input('status', 'all');
        $typeFilter   = $request->input('type', 'all');
        $search       = $request->input('search', '');
        $showArchived = $request->boolean('archived', false);

        $user        = auth()->user();
        $allowedDivs = $this->allowedDivCodes($user);

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
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('pjct_name', 'like', "%{$search}%")
                  ->orWhere('pjct_client', 'like', "%{$search}%")
                  ->orWhere('pjct_area', 'like', "%{$search}%")
                  ->orWhere('pjct_contract', 'like', "%{$search}%");
            });
        }

        $projects = $query->withCount('assets')->orderByRaw('pjct_codate DESC')->orderBy('id')->get();

        $kpiQuery = PjctMain::query();
        if ($allowedDivs !== null) {
            $kpiQuery->whereIn('pjct_div', $allowedDivs);
        }
        $allForKpi = $kpiQuery->get();
        $kpi = [
            'total'  => $allForKpi->count(),
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
            'yearFilter', 'statusFilter', 'typeFilter', 'search', 'showArchived',
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

    private const UNIT_DIV_MAP = [
        'Technology Operation & Maintenance' => ['TC'],
        'Equipment Operation & Maintenance'  => ['EQ', 'EQREG1', 'EQREG2', 'EQREG3'],
        'Technology Commercial'              => ['TCC', 'TC'],
        'Equipment Commercial'               => ['EQC', 'EQ', 'EQREG1', 'EQREG2', 'EQREG3'],
    ];

    // Returns null = no filter (sees all), array = whitelist of pjct_div codes
    private function allowedDivCodes(User $user): ?array
    {
        // Super Admin (role=superadmin) and VIP see every division, unrestricted.
        if ($user->isVip() || $user->isSuperAdmin()) {
            return null;
        }

        $codes = collect();

        if ($user->user_unit && isset(self::UNIT_DIV_MAP[$user->user_unit])) {
            $codes = $codes->merge(self::UNIT_DIV_MAP[$user->user_unit]);
        }

        $codes = $codes->merge($this->subordinateDivCodes($user->id));

        $result = $codes->unique()->values()->all();

        return empty($result) ? null : $result;
    }

    private function subordinateDivCodes(string $userId): Collection
    {
        $codes = collect();
        $subs  = User::where('user_parid', $userId)->get();

        foreach ($subs as $sub) {
            if ($sub->user_unit && isset(self::UNIT_DIV_MAP[$sub->user_unit])) {
                $codes = $codes->merge(self::UNIT_DIV_MAP[$sub->user_unit]);
            }
            $codes = $codes->merge($this->subordinateDivCodes($sub->id));
        }

        return $codes;
    }

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
            'pjct_status'      => 'required|in:OG,HVR,DLY,END',
            'pjct_misc'        => 'nullable|string|max:1000',
        ]);
    }
}
