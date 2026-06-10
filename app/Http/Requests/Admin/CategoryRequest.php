<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore(optional($this->route('category'))->id)->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'parent_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
