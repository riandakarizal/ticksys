<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique('devices', 'name')->ignore(optional($this->route('device'))->id)->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('team_id', request('team_id')))],
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
