<?php

namespace App\Http\Requests\Ticket;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Ticket::class) ?? false;
    }

    public function rules(): array
    {
        $allowedDivs = $this->user()->allowedDivCodes();

        return [
            // Project harus di divisi teknisi — dicek di server, bukan hanya disaring di dropdown.
            'pjct_id' => ['required', 'string', Rule::exists('pjct_main', 'id')->where(
                fn ($query) => $allowedDivs === null ? $query : $query->whereIn('pjct_div', $allowedDivs)
            )],
            'issue_type' => ['required', Rule::in(array_keys(Ticket::ISSUE_TYPES))],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'reporter_name' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->whereNull('parent_id'))],
            'subcategory_id' => ['nullable', Rule::exists('categories', 'id')],
            // Device wajib untuk kerusakan/accident, opsional untuk masalah sistem; harus milik project.
            'ast_ids' => [
                Rule::requiredIf(fn () => in_array($this->input('issue_type'), Ticket::ISSUE_TYPES_REQUIRING_DEVICE, true)),
                'array',
            ],
            'ast_ids.*' => ['string', 'distinct', Rule::exists('ast_main', 'id')->where('ast_pjctid', (string) $this->input('pjct_id'))],
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => TicketAttachment::FILE_RULES,
        ];
    }

    public function messages(): array
    {
        return [
            'pjct_id.exists' => 'Project tidak ditemukan atau di luar divisi Anda.',
            'ast_ids.required' => 'Pilih minimal satu device yang rusak untuk jenis masalah ini.',
            'ast_ids.*.exists' => 'Device yang dipilih bukan milik project ini.',
            'documents.required' => 'Lampirkan dokumen laporan kerusakan / kronologi.',
            'documents.*.max' => 'Ukuran dokumen maksimal 10 MB per file.',
            'documents.*.mimes' => 'Dokumen harus berupa PDF, JPG, PNG, Word, atau Excel.',
        ];
    }

    public function attributes(): array
    {
        return [
            'pjct_id' => 'project',
            'issue_type' => 'jenis masalah',
            'subject' => 'judul',
            'description' => 'kronologi',
            'priority' => 'prioritas',
            'documents' => 'dokumen kronologi',
        ];
    }
}
