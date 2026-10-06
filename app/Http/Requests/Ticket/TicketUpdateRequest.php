<?php

namespace App\Http\Requests\Ticket;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Ticket::STATUSES)],
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('user_role', ['siteadmin', 'admin', 'superadmin']))],
            'pjct_id' => ['required', 'string', Rule::exists('pjct_main', 'id')],
            'ast_id' => ['nullable', 'string', Rule::exists('ast_main', 'id')],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->whereNull('parent_id'))],
            'subcategory_id' => ['nullable', Rule::exists('categories', 'id')],
        ];
    }
}
