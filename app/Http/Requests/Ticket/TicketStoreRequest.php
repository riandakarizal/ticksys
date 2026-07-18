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
        $companyId = auth()->user()->company_id;

        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            'device_id' => ['required', Rule::exists('devices', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'team_id' => ['required', Rule::exists('teams', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('parent_id'))],
            'subcategory_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'requester_id' => [auth()->user()->isUser() ? 'nullable' : 'required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('role', 'user'))],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->whereIn('role', ['siteadmin', 'admin', 'superadmin']))],
            'sla_policy_id' => ['nullable', Rule::exists('sla_policies', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'tags' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,txt,log,doc,docx,xls,xlsx,csv'],
        ];
    }
}
