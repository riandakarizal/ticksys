<?php

namespace App\Http\Requests\Ticket;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Ubah detail tiket — status tidak lewat sini, tapi lewat TicketTransitionRequest. */
class TicketUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('ticket')) ?? false;
    }

    public function rules(): array
    {
        /** @var Ticket $ticket */
        $ticket = $this->route('ticket');

        return [
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            'issue_type' => ['required', Rule::in(array_keys(Ticket::ISSUE_TYPES))],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->whereNull('parent_id'))],
            'subcategory_id' => ['nullable', Rule::exists('categories', 'id')],
            'ast_ids' => [
                Rule::requiredIf(fn () => in_array($this->input('issue_type'), Ticket::ISSUE_TYPES_REQUIRING_DEVICE, true)),
                'array',
            ],
            'ast_ids.*' => ['string', 'distinct', Rule::exists('ast_main', 'id')->where('ast_pjctid', (string) $ticket->pjct_id)],
        ];
    }

    public function messages(): array
    {
        return [
            'ast_ids.required' => 'Pilih minimal satu device yang rusak untuk jenis masalah ini.',
            'ast_ids.*.exists' => 'Device yang dipilih bukan milik project ticket ini.',
        ];
    }
}
