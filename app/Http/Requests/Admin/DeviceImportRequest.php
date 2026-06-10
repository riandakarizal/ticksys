<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isAdmin();
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'team_id' => ['required', Rule::exists('teams', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ];
    }
}
