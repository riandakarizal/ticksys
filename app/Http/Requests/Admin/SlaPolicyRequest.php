<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SlaPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'response_minutes' => ['required', 'integer', 'min:1'],
            'resolution_minutes' => ['required', 'integer', 'min:1'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
