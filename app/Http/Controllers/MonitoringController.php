<?php

namespace App\Http\Controllers;

use App\Models\EqtChangeLog;
use App\Models\EqtHandover;
use App\Models\EqtMaintenance;
use App\Models\EqtProject;
use App\Models\EqtVehicle;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function index(Request $request)
    {
        $yearFilter    = $request->input('year', 'all');
        $statusFilter  = $request->input('status', 'all');
        $docFilter     = $request->input('doc', 'all');
        $search        = $request->input('search', '');
        $showArchived  = $request->boolean('archived', false);

        $projectQuery = $showArchived ? EqtProject::withTrashed() : EqtProject::query();
        if ($yearFilter !== 'all') {
            $projectQuery->where('tahun', $yearFilter);
        }
        if ($statusFilter !== 'all') {
            $projectQuery->where('status', $statusFilter);
        }
        if ($search) {
            $projectQuery->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('mitra', 'like', "%{$search}%")
                  ->orWhere('area', 'like', "%{$search}%")
                  ->orWhere('pemberi_kerja', 'like', "%{$search}%");
            });
        }

        $allProjects  = $projectQuery->orderBy('tahun', 'desc')->orderBy('id')->get();
        $eqProjects   = $allProjects->where('type', 'EQ');
        $techProjects = $allProjects->where('type', 'TECH');

        if ($docFilter !== 'all') {
            $eqProjects   = $eqProjects->filter(fn($p) => $this->matchesDocFilter($p, $docFilter));
            $techProjects = $techProjects->filter(fn($p) => $this->matchesDocFilter($p, $docFilter));
        }

        $handoverQuery    = $showArchived ? EqtHandover::withTrashed() : EqtHandover::query();
        $vehicleQuery     = $showArchived ? EqtVehicle::withTrashed() : EqtVehicle::query();
        $maintenanceQuery = $showArchived ? EqtMaintenance::withTrashed() : EqtMaintenance::query();

        $handovers    = $handoverQuery->orderBy('tahun', 'desc')->orderBy('id')->get();
        $vehicles     = $vehicleQuery->orderByRaw("CASE status_pajak WHEN 'EXPIRED' THEN 1 WHEN 'SOON' THEN 2 WHEN 'OK' THEN 3 ELSE 4 END")->orderBy('pkb_date')->get();
        $maintenances = $maintenanceQuery->orderBy('id')->get();

        // KPI uses only non-archived
        $allForKpi = EqtProject::all();
        $hoCount   = EqtHandover::count();
        $kpi = [
            'total'          => $allForKpi->count() + $hoCount,
            'ongoing'        => $allForKpi->where('status', 'ON GOING')->count(),
            'pending'        => $allForKpi->whereIn('status', ['PENDING'])->count(),
            'attn'           => $allForKpi->whereIn('status', ['NO KONTRAK', 'OUTSTANDING'])->count(),
            'doc_incomplete' => $allForKpi->filter(function ($p) {
                                    $score = $p->docScore()['score'];
                                    return $score !== -1 && $score < 1.0;
                                })->count(),
            'pajak_alert'    => EqtVehicle::whereIn('status_pajak', ['EXPIRED', 'SOON'])->count(),
            'maint_due'      => EqtMaintenance::where(function ($q) {
                                    $q->whereDate('next_service', '<=', now()->addDays(30))
                                      ->orWhere('status_maint', 'OUTSTANDING');
                                })->count(),
            'eq_count'       => $allForKpi->where('type', 'EQ')->count(),
            'tech_count'     => $allForKpi->where('type', 'TECH')->count(),
            'ho_count'       => $hoCount,
        ];

        // Last import info
        $lastImport = EqtChangeLog::where('source', 'import')
            ->with('user')
            ->latest('created_at')
            ->first();

        // Riwayat (admin only)
        $changeLogs = [];
        if (auth()->user()?->isAdmin()) {
            $changeLogs = EqtChangeLog::with('user')
                ->when($request->input('log_type'), fn($q, $t) => $q->where('record_type', $t))
                ->when($request->input('log_action'), fn($q, $a) => $q->where('action', $a))
                ->orderByDesc('created_at')
                ->limit(200)
                ->get();
        }

        return view('monitoring.index', compact(
            'eqProjects', 'techProjects', 'handovers', 'vehicles', 'maintenances', 'kpi',
            'yearFilter', 'statusFilter', 'docFilter', 'search', 'showArchived',
            'lastImport', 'changeLogs'
        ));
    }

    public function store(Request $request, string $type)
    {
        $this->validateType($type);
        $user = auth()->user();

        $record = match ($type) {
            'projects'     => EqtProject::create($this->projectData($request)),
            'handovers'    => EqtHandover::create($this->handoverData($request)),
            'vehicles'     => EqtVehicle::create($this->vehicleData($request)),
            'maintenances' => EqtMaintenance::create($this->maintenanceData($request)),
        };

        EqtChangeLog::record(
            $user,
            $this->logType($type),
            $record->id,
            $this->recordLabel($record, $type),
            'created',
            null,
            $record->toArray(),
        );

        return back()->with('success', 'Record added successfully.');
    }

    public function update(Request $request, string $type, int $id)
    {
        $this->validateType($type);
        $user = auth()->user();

        $record = match ($type) {
            'projects'     => EqtProject::findOrFail($id),
            'handovers'    => EqtHandover::findOrFail($id),
            'vehicles'     => EqtVehicle::findOrFail($id),
            'maintenances' => EqtMaintenance::findOrFail($id),
        };

        $old = $record->toArray();
        $data = match ($type) {
            'projects'     => $this->projectData($request),
            'handovers'    => $this->handoverData($request),
            'vehicles'     => $this->vehicleData($request),
            'maintenances' => $this->maintenanceData($request),
        };
        $record->update($data);

        EqtChangeLog::record(
            $user,
            $this->logType($type),
            $record->id,
            $this->recordLabel($record, $type),
            'updated',
            $old,
            $record->fresh()->toArray(),
        );

        return back()->with('success', 'Record updated successfully.');
    }

    public function destroy(string $type, int $id)
    {
        $this->validateType($type);
        $user = auth()->user();

        $record = match ($type) {
            'projects'     => EqtProject::findOrFail($id),
            'handovers'    => EqtHandover::findOrFail($id),
            'vehicles'     => EqtVehicle::findOrFail($id),
            'maintenances' => EqtMaintenance::findOrFail($id),
        };

        $record->delete(); // soft delete

        EqtChangeLog::record(
            $user,
            $this->logType($type),
            $record->id,
            $this->recordLabel($record, $type),
            'archived',
        );

        return back()->with('success', 'Record archived successfully.');
    }

    public function restore(string $type, int $id)
    {
        $this->validateType($type);
        $user = auth()->user();

        $record = match ($type) {
            'projects'     => EqtProject::withTrashed()->findOrFail($id),
            'handovers'    => EqtHandover::withTrashed()->findOrFail($id),
            'vehicles'     => EqtVehicle::withTrashed()->findOrFail($id),
            'maintenances' => EqtMaintenance::withTrashed()->findOrFail($id),
        };

        $record->restore();

        EqtChangeLog::record(
            $user,
            $this->logType($type),
            $record->id,
            $this->recordLabel($record, $type),
            'restored',
        );

        return back()->with('success', 'Record restored successfully.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function validateType(string $type): void
    {
        abort_unless(in_array($type, ['projects', 'handovers', 'vehicles', 'maintenances']), 404);
    }

    private function logType(string $type): string
    {
        return match ($type) {
            'projects'     => 'project',
            'handovers'    => 'handover',
            'vehicles'     => 'vehicle',
            'maintenances' => 'maintenance',
        };
    }

    private function recordLabel($record, string $type): string
    {
        return match ($type) {
            'projects'     => ($record->nama ?? '') . ' (' . ($record->tahun ?? '') . ')',
            'handovers'    => ($record->nama ?? '') . ' (' . ($record->tahun ?? '') . ')',
            'vehicles'     => $record->nopol ?? '',
            'maintenances' => ($record->nama_alat ?? '') . ' - ' . ($record->area ?? ''),
            default        => (string) $record->id,
        };
    }

    private function matchesDocFilter(EqtProject $p, string $filter): bool
    {
        $score = $p->docScore()['score'];
        if ($score === -1) return false;
        return match ($filter) {
            'complete' => $score === 1.0,
            'partial'  => $score > 0 && $score < 1,
            'none'     => $score === 0.0,
            default    => true,
        };
    }

    private function projectData(Request $request): array
    {
        $data = $request->validate([
            'type'            => 'required|in:EQ,TECH',
            'tahun'           => 'required|integer|min:2000|max:2100',
            'nama'            => 'required|string|max:500',
            'lob'             => 'nullable|string|max:50',
            'pemberi_kerja'   => 'nullable|string|max:200',
            'area'            => 'nullable|string|max:200',
            'mitra'           => 'nullable|string|max:300',
            'nilai_pekerjaan' => 'nullable|integer|min:0',
            'nilai_mitra'     => 'nullable|integer|min:0',
            'serapan'         => 'nullable|integer|min:0',
            'start_date'      => 'nullable|string|max:20',
            'end_date'        => 'nullable|string|max:20',
            'no_kontrak'      => 'nullable|string|max:200',
            'docs'            => 'nullable|array',
            'docs.*'          => 'nullable|integer|in:-1,0,1',
            'status'          => 'required|string|max:50',
            'keterangan'      => 'nullable|string|max:1000',
        ]);
        $data['nilai_pekerjaan'] = $data['nilai_pekerjaan'] ?? 0;
        $data['nilai_mitra']     = $data['nilai_mitra'] ?? 0;
        $data['serapan']         = $data['serapan'] ?? 0;
        return $data;
    }

    private function handoverData(Request $request): array
    {
        return $request->validate([
            'tahun'         => 'required|integer|min:2000|max:2100',
            'nama'          => 'required|string|max:500',
            'lob'           => 'nullable|string|max:50',
            'pemberi_kerja' => 'nullable|string|max:200',
            'area'          => 'nullable|string|max:200',
            'mitra'         => 'nullable|string|max:300',
            'status'        => 'required|string|max:50',
            'keterangan'    => 'nullable|string|max:1000',
        ]);
    }

    private function vehicleData(Request $request): array
    {
        return $request->validate([
            'nopol'        => 'required|string|max:20',
            'jenis'        => 'nullable|string|max:100',
            'customer'     => 'nullable|string|max:200',
            'vendor'       => 'nullable|string|max:200',
            'no_kontrak'   => 'nullable|string|max:200',
            'pkb_date'     => 'nullable|string|max:20',
            'nilai_pkb'    => 'nullable|integer|min:0',
            'status_pajak' => 'required|in:EXPIRED,SOON,OK,N/A',
            'keterangan'   => 'nullable|string|max:500',
        ]);
    }

    private function maintenanceData(Request $request): array
    {
        return $request->validate([
            'nama_alat'    => 'required|string|max:300',
            'area'         => 'nullable|string|max:100',
            'mitra'        => 'nullable|string|max:200',
            'no_kontrak'   => 'nullable|string|max:200',
            'tipe_alat'    => 'nullable|string|max:100',
            'last_service' => 'nullable|string|max:20',
            'next_service' => 'nullable|string|max:20',
            'status_maint' => 'required|string|max:50',
            'keterangan'   => 'nullable|string|max:500',
        ]);
    }
}
