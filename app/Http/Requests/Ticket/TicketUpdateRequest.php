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
        $companyId = auth()->user()->company_id;

        return [
            'status' => ['required', Rule::in(Ticket::STATUSES)],
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->whereIn('role', ['agent', 'supervisor', 'admin']))],
            'team_id' => ['required', Rule::exists('teams', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'device_id' => ['required', Rule::exists('devices', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('parent_id'))],
            'subcategory_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'requester_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('role', 'client'))],
        ];
    }
}
