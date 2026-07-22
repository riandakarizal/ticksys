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
        $data = $request->validated();

        User::create([
            'user_empid'  => $data['user_empid'],
            'user_name'   => $data['user_name'],
            'user_email'  => $data['user_email'],
            'user_pass'   => $data['user_pass'],
            'user_level'  => $data['user_level'],
            'user_role'   => $data['user_role'],
            'user_unit'   => $data['user_unit'],
            'user_div'    => $data['user_div'],
            'user_parid'  => $data['user_parid'] ?? '',
            'user_status' => $data['user_status'],
        ]);

        return $this->respond($request, 'User created successfully.', route('admin.users.index'));
    }

    public function update(UserUpdateRequest $request, User $managedUser): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        $managedUser->fill([
            'user_empid'  => $data['user_empid'],
            'user_name'   => $data['user_name'],
            'user_email'  => $data['user_email'],
            'user_level'  => $data['user_level'],
            'user_role'   => $data['user_role'],
            'user_unit'   => $data['user_unit'],
            'user_div'    => $data['user_div'],
            'user_parid'  => $data['user_parid'] ?? '',
            'user_status' => $data['user_status'],
        ]);

        if (! empty($data['user_pass'])) {
            $managedUser->user_pass = bcrypt($data['user_pass']);
        }

        $managedUser->save();

        return $this->respond($request, 'User updated successfully.', route('admin.users.index'));
    }

    public function destroy(Request $request, User $managedUser): RedirectResponse|JsonResponse
    {
        abort_if($managedUser->id === auth()->id(), 422, 'Tidak dapat menghapus akun sendiri.');

        $managedUser->teams()->detach();
        $managedUser->delete();

        return $this->respond($request, 'User deleted successfully.', route('admin.users.index'));
    }
}
