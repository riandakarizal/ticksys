<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'user_empid'  => ['required', 'string', 'max:20'],
            'user_name'   => ['required', 'string', 'max:20'],
            'user_email'  => ['required', 'email', 'max:225', Rule::unique('users', 'user_email')],
            'user_pass'   => ['required', 'string', 'min:6'],
            'user_level'  => ['required', 'in:L1,L2,L3,L4,L5,L6,L7'],
            'user_role'   => ['required', 'in:user,siteadmin,admin,superadmin,vip,fin,qhsse'],
            // Rule::in (bukan string 'in:') — nama unit QHSSE mengandung koma.
            'user_unit'   => ['required', Rule::in(['-', 'Technology Operation & Maintenance', 'Equipment Operation & Maintenance', 'Technology Commercial', 'Equipment Commercial', 'Asset', 'Quality, Health, Safety, Security & Environment'])],
            'user_div'    => ['required', 'in:-,Equipment & Technology Commercial,Equipment & Technology Operation & Maintenance,Finance'],
            'user_parid'  => ['nullable', 'string', Rule::exists('users', 'id')],
            'user_status' => ['required', 'in:active,inactive'],
        ];
    }
}
