<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
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
            'code' => ['nullable', 'string', 'max:50', Rule::unique('teams', 'code')->ignore(optional($this->route('team'))->id)->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'description' => ['nullable', 'string'],
            'lead_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereIn('role', ['admin', 'supervisor']))],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => [Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
        ];
    }
}
