<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAttachment extends Model
{
    /** Jenis dokumen tiket, urut sesuai alur. Lampiran lama (doc_type null) dianggap 'lampiran'. */
    public const DOC_TYPES = [
        'kronologi' => 'Laporan Kerusakan / Kronologi',
        'ba_eskalasi' => 'BA Serah Terima ke Pusat',
        'ba_pengembalian' => 'BA Serah Terima dari Pusat',
        'penyelesaian' => 'Dokumen Penyelesaian',
        'lampiran' => 'Lampiran',
    ];

    /** Satu aturan file untuk semua dokumen tiket (scan BA biasanya PDF/JPG). */
    public const FILE_RULES = ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'];

    protected $fillable = [
        'ticket_id',
        'ticket_message_id',
        'doc_type',
        'user_id',
        'original_name',
        'path',
        'mime_type',
        'size',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function docTypeKey(): string
    {
        return array_key_exists((string) $this->doc_type, self::DOC_TYPES) ? $this->doc_type : 'lampiran';
    }

    public function docTypeLabel(): string
    {
        return self::DOC_TYPES[$this->docTypeKey()];
    }
}
