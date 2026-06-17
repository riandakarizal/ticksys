<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminController;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends AdminController
{
    public function index(): View
    {
        return view('admin.users', $this->viewData('Users', 'Manage Users and their access across projects.'));
    }

    public function store(UserStoreRequest $request): RedirectResponse|JsonResponse
    {
        $authUser = auth()->user();
        $data     = $request->validated();

        $this->ensureProjectAssignmentForRole($data['role'], $data['project_ids'] ?? []);

        $user = User::create([
            'company_id' => $authUser->company_id,
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => $data['password'],
            'role'      => $data['role'],
            'job_title' => $data['job_title'] ?? null,
            'phone'     => $data['phone'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $user->teams()->sync($data['project_ids'] ?? []);

        return $this->respond($request, 'User created successfully.', route('admin.users.index'));
    }

    public function update(UserUpdateRequest $request, User $managedUser): RedirectResponse|JsonResponse
    {
        $this->ensureCompanyRecord($managedUser);

        $data = $request->validated();
        $this->ensureProjectAssignmentForRole($data['role'], $data['project_ids'] ?? []);

        $managedUser->fill([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'role'      => $data['role'],
            'job_title' => $data['job_title'] ?? null,
            'phone'     => $data['phone'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (! empty($data['password'])) {
            $managedUser->password = $data['password'];
        }

        $managedUser->save();
        $managedUser->teams()->sync($data['project_ids'] ?? []);

        return $this->respond($request, 'User updated successfully.', route('admin.users.index'));
    }

    public function destroy(Request $request, User $managedUser): RedirectResponse|JsonResponse
    {
        $this->ensureCompanyRecord($managedUser);
        abort_if($managedUser->id === auth()->id(), 422, 'Tidak dapat menghapus akun sendiri.');

        $managedUser->teams()->detach();
        $managedUser->delete();

        return $this->respond($request, 'User deleted successfully.', route('admin.users.index'));
    }
}
