<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isAdmin();
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('managedUser')->id)],
            'role' => ['required', 'in:client,agent,supervisor,admin'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:6'],
            'is_active' => ['nullable', 'boolean'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => [Rule::exists('teams', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
        ];
    }
}
