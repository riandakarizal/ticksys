<?php

namespace App\Http\Requests\Ticket;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && ! auth()->user()->isVip();
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            // Project PRISM — menentukan client (pjct_client) secara otomatis, lihat
            // TicketManager::createTicket().
            'pjct_id' => ['required', 'string', Rule::exists('pjct_main', 'id')],
            // Aset PRISM yang bermasalah — opsional, karena mayoritas project tidak
            // memiliki aset tercatat di ast_main (lihat docs/PRISM-DATABASE.md).
            'ast_id' => ['nullable', 'string', Rule::exists('ast_main', 'id')],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->whereNull('parent_id'))],
            'subcategory_id' => ['nullable', Rule::exists('categories', 'id')],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('user_role', ['siteadmin', 'admin', 'superadmin']))],
            'sla_policy_id' => ['nullable', Rule::exists('sla_policies', 'id')],
            'tags' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,txt,log,doc,docx,xls,xlsx,csv'],
        ];
    }
}
