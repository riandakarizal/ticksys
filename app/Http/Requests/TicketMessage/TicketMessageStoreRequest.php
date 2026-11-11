<?php

namespace App\Http\Requests\TicketMessage;

use App\Models\TicketAttachment;
use Illuminate\Foundation\Http\FormRequest;

class TicketMessageStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('comment', $this->route('ticket')) ?? false;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => TicketAttachment::FILE_RULES,
        ];
    }

    public function messages(): array
    {
        return [
            'attachments.*.max' => 'Ukuran lampiran maksimal 10 MB per file.',
            'attachments.*.mimes' => 'Lampiran harus berupa PDF, JPG, PNG, Word, atau Excel.',
        ];
    }
}
