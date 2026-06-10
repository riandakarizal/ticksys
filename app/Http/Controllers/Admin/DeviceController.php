<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminController;
use App\Http\Requests\Admin\DeviceImportRequest;
use App\Http\Requests\Admin\DeviceRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeviceController extends AdminController
{
    public function index(): View
    {
        return view('admin.devices', $this->viewData('Devices', 'Manage project device inventory, repair status, and upload device lists in bulk.'));
    }

    public function store(DeviceRequest $request): RedirectResponse|JsonResponse
    {
        $authUser = Auth::user();
        $data = $request->validated();

        Device::create([
            'tenant_id' => $authUser->tenant_id,
            'team_id' => $data['team_id'],
            'name' => $data['name'],
            'asset_code' => $data['asset_code'] ?? null,
            'device_type' => $data['device_type'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->respond($request, 'Device created successfully.', route('admin.devices.index'));
    }

    public function update(DeviceRequest $request, Device $device): RedirectResponse|JsonResponse
    {
        $this->ensureTenantRecord($device);
        $data = $request->validated();

        $device->update([
            'team_id' => $data['team_id'],
            'name' => $data['name'],
            'asset_code' => $data['asset_code'] ?? null,
            'device_type' => $data['device_type'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->respond($request, 'Device updated successfully.', route('admin.devices.index'));
    }

    public function destroy(Request $request, Device $device): RedirectResponse|JsonResponse
    {
        $this->ensureTenantRecord($device);
        $device->delete();

        return $this->respond($request, 'Device deleted successfully.', route('admin.devices.index'));
    }

    public function import(DeviceImportRequest $request): RedirectResponse|JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $data = $request->validated();
        $rows = $this->parseDeviceCsv($request->file('file')->getRealPath());

        if ($rows->isEmpty()) {
            abort(422, 'The CSV file contains no importable device records.');
        }

        $createdCount = 0;

        foreach ($rows as $row) {
            if (blank($row['name'] ?? null)) {
                continue;
            }

            $match = ! empty($row['asset_code'])
                ? ['tenant_id' => $tenantId, 'team_id' => (int) $data['team_id'], 'asset_code' => $row['asset_code']]
                : ['tenant_id' => $tenantId, 'team_id' => (int) $data['team_id'], 'name' => $row['name']];

            $device = Device::firstOrNew($match);
            $device->fill([
                'tenant_id' => $tenantId,
                'team_id' => (int) $data['team_id'],
                'name' => $row['name'],
                'asset_code' => $row['asset_code'] ?? null,
                'device_type' => $row['device_type'] ?? null,
                'serial_number' => $row['serial_number'] ?? null,
                'ip_address' => $row['ip_address'] ?? null,
                'location' => $row['location'] ?? null,
                'notes' => $row['notes'] ?? null,
                'is_active' => true,
            ]);

            $createdCount += $device->exists ? 0 : 1;
            $device->save();
        }

        return $this->respond($request, 'Device upload complete. '.$rows->count().' rows processed, '.$createdCount.' new devices added.', route('admin.devices.index'));
    }

    public function downloadTemplate(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['name', 'asset_code', 'device_type', 'serial_number', 'ip_address', 'location', 'notes']);
            fputcsv($handle, ['Laptop Finance-01', 'ACME-LPT-001', 'Laptop', 'SN-LPT-001', '10.10.1.21', 'Finance Floor', 'Primary finance laptop']);
            fputcsv($handle, ['Printer Finance-02', 'ACME-PRN-002', 'Printer', 'SN-PRN-002', '10.10.1.45', 'Finance Floor', 'Shared printer']);
            fclose($handle);
        }, 'device-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $query = Device::query()
            ->where('tenant_id', $tenantId)
            ->with('team:id,name')
            ->withExists(['tickets as has_open_ticket' => fn ($builder) => $builder->where('status', '!=', 'closed')])
            ->orderBy('team_id')
            ->orderBy('name');

        if ($projectId = $request->integer('team_id')) {
            $query->where('team_id', $projectId);
        }

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['project', 'name', 'asset_code', 'device_type', 'serial_number', 'ip_address', 'location', 'status', 'notes']);

            foreach ($query->cursor() as $device) {
                fputcsv($handle, [
                    $device->team?->name,
                    $device->name,
                    $device->asset_code,
                    $device->device_type,
                    $device->serial_number,
                    $device->ip_address,
                    $device->location,
                    $device->operationalStatusLabel(),
                    $device->notes,
                ]);
            }

            fclose($handle);
        }, 'devices-export-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
