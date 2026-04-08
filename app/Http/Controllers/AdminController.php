<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Device;
use App\Models\Ticket;
use App\Models\SlaPolicy;
use App\Models\Team;
use App\Models\User;
use App\Support\ProjectManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    // Parent categories rotate through a small clean palette when no explicit
    // color is provided. Child categories inherit their parent color instead.
    private const CATEGORY_COLOR_PALETTE = [
        '#2563eb',
        '#0f766e',
        '#7c3aed',
        '#ea580c',
        '#0891b2',
        '#16a34a',
        '#be185d',
        '#4f46e5',
    ];

    public function users(): View
    {
        return view('admin.users', $this->viewData('Users', 'Manage Users and their access across projects.'));
    }

    public function slaPolicies(): View
    {
        return view('admin.sla', $this->viewData('SLA Policy', 'Manage response and resolution targets per projects.'));
    }

    public function categories(): View
    {
        return view('admin.categories', $this->viewData('Category', 'Manage category routing, subcategories, and auto assignment.'));
    }

    public function projects(): View
    {
        return view('admin.projects', $this->viewData('Project', 'Projects serve as the primary access boundary for clients, agents, and coordinators.'));
    }

    public function devices(): View
    {
        return view('admin.devices', $this->viewData('Devices', 'Manage project device inventory, repair status, and upload device lists in bulk.'));
    }

    public function projectDevices(Team $team): View
    {
        $this->ensureTenantRecord($team);

        $team->load(['lead:id,name', 'members:id,name,role']);
        $projectDevices = $team->devices()
            ->withCount('tickets')
            ->withExists(['tickets as has_open_ticket' => fn ($query) => $query->where('status', '!=', 'closed')])
            ->orderBy('name')
            ->get();
        $recentTickets = Ticket::query()
            ->where('tenant_id', $team->tenant_id)
            ->where('team_id', $team->id)
            ->with(['device:id,name', 'requester:id,name'])
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.project-devices', array_merge(
            $this->viewData('Project Devices', 'Review device inventory and ticket usage per project.'),
            [
                'project' => $team,
                'projectDevices' => $projectDevices,
                'recentTickets' => $recentTickets,
            ]
        ));
    }

    public function downloadDeviceTemplate(): StreamedResponse
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

    public function exportDevices(Request $request): StreamedResponse
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

    public function storeUser(Request $request): RedirectResponse|JsonResponse
    {
        $authUser = Auth::user();
        $data = $request->validate($this->userRules($authUser->tenant_id));

        $this->ensureProjectAssignmentForRole($data['role'], $data['project_ids'] ?? []);

        $user = User::create([
            'tenant_id' => $authUser->tenant_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'job_title' => $data['job_title'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $user->projects()->sync($data['project_ids'] ?? []);

        return $this->respond($request, 'User baru berhasil dibuat.', route('admin.users.index'));
    }

    public function updateUser(Request $request, User $managedUser): RedirectResponse|JsonResponse
    {
        $this->ensureTenantRecord($managedUser);

        $authUser = Auth::user();
        $data = $request->validate($this->userRules($authUser->tenant_id, $managedUser->id, false));

        $this->ensureProjectAssignmentForRole($data['role'], $data['project_ids'] ?? []);

        $managedUser->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'job_title' => $data['job_title'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (! empty($data['password'])) {
            $managedUser->password = $data['password'];
        }

        $managedUser->save();
        $managedUser->projects()->sync($data['project_ids'] ?? []);

        return $this->respond($request, 'User berhasil diperbarui.', route('admin.users.index'));
    }

    public function destroyUser(Request $request, User $managedUser): RedirectResponse|JsonResponse
    {
        $this->ensureTenantRecord($managedUser);

        $managedUser->projects()->detach();
        $managedUser->delete();

        return $this->respond($request, 'User berhasil dihapus.', route('admin.users.index'));
    }

    public function storeProject(Request $request, ProjectManager $projectManager): RedirectResponse|JsonResponse
    {
        $authUser = Auth::user();
        $data = $request->validate($this->projectRules($authUser->tenant_id));

        $projectManager->create($authUser->tenant_id, $data);

        return $this->respond($request, 'Project berhasil dibuat.', route('admin.projects.index'));
    }

    public function updateProject(Request $request, Team $team, ProjectManager $projectManager): RedirectResponse|JsonResponse
    {
        $this->ensureTenantRecord($team);
        $data = $request->validate($this->projectRules(Auth::user()->tenant_id, $team->id));

        $projectManager->update($team, $data);

        return $this->respond($request, 'Project berhasil diperbarui.', route('admin.projects.index'));
    }

    public function destroyProject(Request $request, Team $team, ProjectManager $projectManager): RedirectResponse|JsonResponse
    {
        $this->ensureTenantRecord($team);
        $projectManager->delete($team);

        return $this->respond($request, 'Project berhasil dihapus.', route('admin.projects.index'));
    }

    public function storeCategory(Request $request): RedirectResponse|JsonResponse
    {
        $authUser = Auth::user();
        $data = $request->validate($this->categoryRules($authUser->tenant_id));
        $parentCategory = ! empty($data['parent_id'])
            ? Category::query()->where('tenant_id', $authUser->tenant_id)->find($data['parent_id'])
            : null;

        $category = Category::create([
            'tenant_id' => $authUser->tenant_id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'parent_id' => $data['parent_id'] ?? null,
            'color' => $this->resolveCategoryColor($authUser->tenant_id, $data['color'] ?? null, $parentCategory),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->respond($request, 'Category berhasil dibuat.', route('admin.categories.index'));
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse|JsonResponse
    {
        $this->ensureTenantRecord($category);
        $data = $request->validate($this->categoryRules(Auth::user()->tenant_id, $category->id));

        if (! empty($data['parent_id']) && (int) $data['parent_id'] === $category->id) {
            throw ValidationException::withMessages(['parent_id' => 'Category tidak bisa menjadi parent dirinya sendiri.']);
        }

        $parentCategory = ! empty($data['parent_id'])
            ? Category::query()->where('tenant_id', Auth::user()->tenant_id)->find($data['parent_id'])
            : null;

        $category->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'parent_id' => $data['parent_id'] ?? null,
            'color' => $this->resolveCategoryColor(
                Auth::user()->tenant_id,
                $data['color'] ?? null,
                $parentCategory,
                $category->color
            ),
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (blank($category->parent_id)) {
            $category->children()->update(['color' => $category->color]);
        }

        return $this->respond($request, 'Category berhasil diperbarui.', route('admin.categories.index'));
    }

    public function destroyCategory(Request $request, Category $category): RedirectResponse|JsonResponse
    {
        $this->ensureTenantRecord($category);
        $category->projects()->detach();
        $category->delete();

        return $this->respond($request, 'Category berhasil dihapus.', route('admin.categories.index'));
    }

    public function storeSla(Request $request): RedirectResponse|JsonResponse
    {
        $authUser = Auth::user();
        $data = $request->validate($this->slaRules());

        if ($request->boolean('is_default')) {
            SlaPolicy::query()->where('tenant_id', $authUser->tenant_id)->update(['is_default' => false]);
        }

        SlaPolicy::create([
            'tenant_id' => $authUser->tenant_id,
            'name' => $data['name'],
            'response_minutes' => $data['response_minutes'],
            'resolution_minutes' => $data['resolution_minutes'],
            'is_default' => $request->boolean('is_default'),
        ]);

        return $this->respond($request, 'SLA berhasil dibuat.', route('admin.sla.index'));
    }

    public function updateSla(Request $request, SlaPolicy $slaPolicy): RedirectResponse|JsonResponse
    {
        $this->ensureTenantRecord($slaPolicy);
        $data = $request->validate($this->slaRules());

        if ($request->boolean('is_default')) {
            SlaPolicy::query()
                ->where('tenant_id', Auth::user()->tenant_id)
                ->whereKeyNot($slaPolicy->id)
                ->update(['is_default' => false]);
        }

        $slaPolicy->update([
            'name' => $data['name'],
            'response_minutes' => $data['response_minutes'],
            'resolution_minutes' => $data['resolution_minutes'],
            'is_default' => $request->boolean('is_default'),
        ]);

        return $this->respond($request, 'SLA berhasil diperbarui.', route('admin.sla.index'));
    }

    public function destroySla(Request $request, SlaPolicy $slaPolicy): RedirectResponse|JsonResponse
    {
        $this->ensureTenantRecord($slaPolicy);
        $slaPolicy->delete();

        return $this->respond($request, 'SLA berhasil dihapus.', route('admin.sla.index'));
    }

    public function storeDevice(Request $request): RedirectResponse|JsonResponse
    {
        $authUser = Auth::user();
        $data = $request->validate($this->deviceRules($authUser->tenant_id));

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

        return $this->respond($request, 'Perangkat berhasil dibuat.', route('admin.devices.index'));
    }

    public function updateDevice(Request $request, Device $device): RedirectResponse|JsonResponse
    {
        $this->ensureTenantRecord($device);
        $data = $request->validate($this->deviceRules(Auth::user()->tenant_id, $device->id));

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

        return $this->respond($request, 'Perangkat berhasil diperbarui.', route('admin.devices.index'));
    }

    public function destroyDevice(Request $request, Device $device): RedirectResponse|JsonResponse
    {
        $this->ensureTenantRecord($device);
        $device->delete();

        return $this->respond($request, 'Perangkat berhasil dihapus.', route('admin.devices.index'));
    }

    public function importDevices(Request $request): RedirectResponse|JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $data = $request->validate([
            'team_id' => ['required', Rule::exists('teams', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $rows = $this->parseDeviceCsv($request->file('file')->getRealPath());
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'File CSV tidak memiliki data perangkat yang bisa diimpor.']);
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

        return $this->respond($request, 'Upload perangkat selesai. '.$rows->count().' baris diproses, '.$createdCount.' perangkat baru ditambahkan.', route('admin.devices.index'));
    }

    private function viewData(string $pageTitle, string $pageDescription): array
    {
        $tenantId = Auth::user()->tenant_id;
        // Centralize admin page data so every submenu reads the same tenant-scoped
        // collections and stays consistent after CRUD actions.
        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->with('projects:id,name')
            ->orderByRaw("FIELD(role, 'admin', 'supervisor', 'agent', 'client')")
            ->orderBy('name')
            ->get();

        $projects = Team::query()
            ->where('tenant_id', $tenantId)
            ->with(['members:id,name,role', 'lead:id,name'])
            ->orderBy('name')
            ->get();

        return [
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'users' => $users,
            'projects' => $projects,
            'categories' => Category::query()->where('tenant_id', $tenantId)->with(['children', 'parent'])->orderBy('name')->get(),
            'slaPolicies' => SlaPolicy::query()->where('tenant_id', $tenantId)->orderByDesc('is_default')->orderBy('name')->get(),
            'devices' => Device::query()
                ->where('tenant_id', $tenantId)
                ->with('team:id,name')
                ->withExists(['tickets as has_open_ticket' => fn ($query) => $query->where('status', '!=', 'closed')])
                ->orderBy('name')
                ->get(),
            'projectUsers' => $users->where('role', '!=', 'admin')->values(),
            'assignableAgents' => $users->whereIn('role', ['admin', 'supervisor', 'agent'])->values(),
            'coordinators' => $users->whereIn('role', ['admin', 'supervisor'])->values(),
        ];
    }

    private function respond(Request $request, string $message, string $redirect): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'redirect' => $redirect,
            ]);
        }

        return redirect($redirect)->with('success', $message);
    }

    private function ensureTenantRecord(object $model): void
    {
        abort_unless($model->tenant_id === Auth::user()->tenant_id, 404);
    }

    private function ensureProjectAssignmentForRole(string $role, array $projectIds): void
    {
        // Project membership is the primary access boundary for non-admin users.
        if ($role !== 'admin' && empty($projectIds)) {
            throw ValidationException::withMessages([
                'project_ids' => 'User non-admin wajib di-assign minimal ke satu project.',
            ]);
        }
    }

    private function parseDeviceCsv(string $path): Collection
    {
        $rows = collect();
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return $rows;
        }

        $headers = null;
        $knownHeaders = ['name', 'asset_code', 'device_type', 'serial_number', 'ip_address', 'location', 'notes'];

        while (($data = fgetcsv($handle)) !== false) {
            $data = array_map(fn ($value) => trim((string) $value), $data);
            if (collect($data)->filter()->isEmpty()) {
                continue;
            }

            if ($headers === null) {
                // Accept either a real CSV header row or a plain template without
                // headers so imports stay forgiving for admin users.
                $normalized = array_map(fn ($value) => strtolower(str_replace([' ', '-'], '_', $value)), $data);
                if (array_intersect($normalized, $knownHeaders)) {
                    $headers = $normalized;
                    continue;
                }

                $headers = ['name', 'asset_code', 'device_type', 'serial_number', 'ip_address', 'location', 'notes'];
            }

            $rows->push(array_replace(array_fill_keys($headers, null), array_combine(array_slice($headers, 0, count($data)), $data)));
        }

        fclose($handle);

        return $rows;
    }

    private function userRules(int $tenantId, ?int $ignoreId = null, bool $passwordRequired = true): array
    {
        $passwordRules = $passwordRequired ? ['required', 'string', 'min:6'] : ['nullable', 'string', 'min:6'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreId)],
            'role' => ['required', 'in:client,agent,supervisor,admin'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => $passwordRules,
            'is_active' => ['nullable', 'boolean'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => [Rule::exists('teams', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
        ];
    }

    private function projectRules(int $tenantId, ?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('teams', 'code')->ignore($ignoreId)->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'description' => ['nullable', 'string'],
            'lead_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereIn('role', ['admin', 'supervisor']))],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => [Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
        ];
    }

    private function categoryRules(int $tenantId, ?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($ignoreId)->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'parent_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function resolveCategoryColor(int $tenantId, ?string $color, ?Category $parentCategory = null, ?string $fallbackColor = null): string
    {
        // Child category color always follows the parent to keep taxonomy color
        // coding stable across the UI.
        if ($parentCategory) {
            return $parentCategory->color ?: $this->defaultCategoryColor($tenantId);
        }

        if (filled($color)) {
            return $color;
        }

        if (filled($fallbackColor)) {
            return $fallbackColor;
        }

        return $this->defaultCategoryColor($tenantId);
    }

    private function defaultCategoryColor(int $tenantId): string
    {
        $parentCount = Category::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('parent_id')
            ->count();

        return self::CATEGORY_COLOR_PALETTE[$parentCount % count(self::CATEGORY_COLOR_PALETTE)];
    }

    private function slaRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'response_minutes' => ['required', 'integer', 'min:1'],
            'resolution_minutes' => ['required', 'integer', 'min:1'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    private function deviceRules(int $tenantId, ?int $ignoreId = null): array
    {
        return [
            'team_id' => ['required', Rule::exists('teams', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'name' => ['required', 'string', 'max:255', Rule::unique('devices', 'name')->ignore($ignoreId)->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('team_id', request('team_id')))],
            'asset_code' => ['nullable', 'string', 'max:100'],
            'device_type' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'ip_address' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}



