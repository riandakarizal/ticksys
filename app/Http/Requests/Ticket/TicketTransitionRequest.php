<?php

namespace App\Http\Requests\Ticket;

use App\Models\TicketAttachment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Input aksi alur tiket. Ability policy = nama aksi (solve/escalate/return/approve/reject).
 * Dokumen wajib: BA serah terima ke pusat (escalate) dan dari pusat (return).
 */
class TicketTransitionRequest extends FormRequest
{
    private const DOCUMENT_REQUIRED = ['escalate', 'return'];

    private const DOCUMENT_ALLOWED = ['solve', 'escalate', 'return'];

    public function authorize(): bool
    {
        return $this->user()?->can($this->action(), $this->route('ticket')) ?? false;
    }

    public function rules(): array
    {
        $action = $this->action();

        $rules = [
            'note' => [$action === 'approve' ? 'nullable' : 'required', 'string', 'max:5000'],
        ];

        if (in_array($action, self::DOCUMENT_ALLOWED, true)) {
            $required = in_array($action, self::DOCUMENT_REQUIRED, true);
            $rules['documents'] = [$required ? 'required' : 'nullable', 'array', $required ? 'min:1' : 'min:0'];
            $rules['documents.*'] = TicketAttachment::FILE_RULES;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'note.required' => match ($this->action()) {
                'solve' => 'Tuliskan tindakan perbaikan yang sudah dilakukan.',
                'escalate' => 'Tuliskan alasan eskalasi ke tim pusat.',
                'return' => 'Tuliskan hasil perbaikan / penggantian unit dari tim pusat.',
                'reject' => 'Tuliskan alasan penolakan.',
                default => 'Catatan wajib diisi.',
            },
            'documents.required' => $this->action() === 'escalate'
                ? 'Lampirkan BA serah terima unit ke tim pusat.'
                : 'Lampirkan BA serah terima unit dari tim pusat ke teknisi.',
            'documents.*.max' => 'Ukuran dokumen maksimal 10 MB per file.',
            'documents.*.mimes' => 'Dokumen harus berupa PDF, JPG, PNG, Word, atau Excel.',
        ];
    }

    public function action(): string
    {
        return (string) $this->route('action');
    }
}
