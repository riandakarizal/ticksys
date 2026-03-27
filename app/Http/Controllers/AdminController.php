<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\SlaPolicy;
use App\Models\Team;
use App\Models\User;
use App\Support\ProjectManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminController extends Controller
{
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
        return view('admin.categories', $this->viewData('Category', 'Manage category routing, subcategories, project scope, and auto assignment.'));
    }

    public function projects(): View
    {
        return view('admin.projects', $this->viewData('Project', 'Projects serve as the primary access boundary for clients, agents, and coordinators.'));
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

        $category = Category::create([
            'tenant_id' => $authUser->tenant_id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'parent_id' => $data['parent_id'] ?? null,
            'auto_assign_user_id' => $data['auto_assign_user_id'] ?? null,
            'color' => $data['color'] ?? '#2563eb',
            'is_active' => $request->boolean('is_active', true),
        ]);

        $category->projects()->sync($data['project_ids'] ?? []);

        return $this->respond($request, 'Category berhasil dibuat.', route('admin.categories.index'));
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse|JsonResponse
    {
        $this->ensureTenantRecord($category);
        $data = $request->validate($this->categoryRules(Auth::user()->tenant_id, $category->id));

        if (! empty($data['parent_id']) && (int) $data['parent_id'] === $category->id) {
            throw ValidationException::withMessages(['parent_id' => 'Category tidak bisa menjadi parent dirinya sendiri.']);
        }

        $category->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'parent_id' => $data['parent_id'] ?? null,
            'auto_assign_user_id' => $data['auto_assign_user_id'] ?? null,
            'color' => $data['color'] ?? '#2563eb',
            'is_active' => $request->boolean('is_active', true),
        ]);

        $category->projects()->sync($data['project_ids'] ?? []);

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

    private function viewData(string $pageTitle, string $pageDescription): array
    {
        $tenantId = Auth::user()->tenant_id;
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
            'categories' => Category::query()->where('tenant_id', $tenantId)->with(['children', 'parent', 'projects:id,name', 'autoAssignUser'])->orderBy('name')->get(),
            'slaPolicies' => SlaPolicy::query()->where('tenant_id', $tenantId)->orderByDesc('is_default')->orderBy('name')->get(),
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
        if ($role !== 'admin' && empty($projectIds)) {
            throw ValidationException::withMessages([
                'project_ids' => 'User non-admin wajib di-assign minimal ke satu project.',
            ]);
        }
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
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => [Rule::exists('teams', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'auto_assign_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereIn('role', ['admin', 'supervisor', 'agent']))],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ];
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
}
