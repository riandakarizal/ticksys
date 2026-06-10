<?php

namespace App\Http\Requests\Ticket;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            'device_id' => ['required', Rule::exists('devices', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'team_id' => ['required', Rule::exists('teams', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('parent_id'))],
            'subcategory_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'requester_id' => [auth()->user()->isClient() ? 'nullable' : 'required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('role', 'client'))],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereIn('role', ['agent', 'supervisor', 'admin']))],
            'sla_policy_id' => ['nullable', Rule::exists('sla_policies', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'tags' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,txt,log,doc,docx,xls,xlsx,csv'],
        ];
    }
}
