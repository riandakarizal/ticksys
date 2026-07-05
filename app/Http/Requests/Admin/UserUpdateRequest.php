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
        $userId = $this->route('managedUser')->id;

        return [
            'user_empid'  => ['required', 'string', 'max:20'],
            'user_name'   => ['required', 'string', 'max:20'],
            'user_email'  => ['required', 'email', 'max:225', Rule::unique('users', 'user_email')->ignore($userId, 'id')],
            'user_pass'   => ['nullable', 'string', 'min:6'],
            'user_level'  => ['required', 'string', 'max:20'],
            'user_role'   => ['required', 'in:client,agent,supervisor,admin,vip'],
            'user_unit'   => ['required', 'string', 'max:225'],
            'user_div'    => ['required', 'string', 'max:225'],
            'user_parid'  => ['required', 'string', 'max:225'],
            'user_status' => ['required', 'in:active,inactive'],
            'project_ids'   => ['nullable', 'array'],
            'project_ids.*' => [Rule::exists('teams', 'id')],
        ];
    }
}
