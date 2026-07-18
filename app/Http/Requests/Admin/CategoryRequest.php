<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin());
    }

    public function rules(): array
    {
        $companyId = auth()->user()->company_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore(optional($this->route('category'))->id)->where(fn ($query) => $query->where('company_id', $companyId))],
            'parent_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
