<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        $companyId = auth()->user()->company_id;

        return [
            'team_id' => ['required', Rule::exists('teams', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'name' => ['required', 'string', 'max:255', Rule::unique('devices', 'name')->ignore(optional($this->route('device'))->id)->where(fn ($query) => $query->where('company_id', $companyId)->where('team_id', request('team_id')))],
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
