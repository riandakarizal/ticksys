<?php

namespace App\Http\Requests\TicketMessage;

use Illuminate\Foundation\Http\FormRequest;

class TicketMessageStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
            'is_internal' => ['nullable', 'boolean'],
            'attachments.*' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,txt,log,doc,docx,xls,xlsx,csv'],
        ];
    }
}
