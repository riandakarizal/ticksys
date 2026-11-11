<?php

namespace App\Models;

use App\Models\Concerns\LogsSystemActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu nomor surat yang sudah dikeluarkan. Nomor dibuat hanya lewat LetterNumberService;
 * setelah itu kode/tahun/bulan/urutan tidak pernah berubah. Salah buat → dibatalkan, bukan dihapus.
 */
class Letter extends Model
{
    use LogsSystemActivity;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    public const ROMAN_MONTHS = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    protected $fillable = [
        'letter_type_id', 'number', 'unit_code', 'code', 'year', 'month', 'seq', 'sub_seq',
        'letter_date', 'subject', 'signer', 'recipient', 'pic', 'pjct_id',
        'file_path', 'file_name', 'file_size',
        'status', 'cancel_reason', 'cancelled_by', 'cancelled_at',
        'source', 'import_note', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'letter_date' => 'date',
            'cancelled_at' => 'datetime',
            'year' => 'integer',
            'month' => 'integer',
            'seq' => 'integer',
            'sub_seq' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public static function formatNumber(string $unit, string $code, int $month, int $year, int $seq, ?int $sub = null): string
    {
        return sprintf(
            'IASS/%s/%s/%s/%d/%s%s',
            $unit,
            $code,
            self::ROMAN_MONTHS[$month] ?? $month,
            $year,
            str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            $sub === null ? '' : '.'.$sub
        );
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LetterType::class, 'letter_type_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PjctMain::class, 'pjct_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isImported(): bool
    {
        return $this->source === 'import';
    }

    public function monthRoman(): string
    {
        return self::ROMAN_MONTHS[$this->month] ?? (string) $this->month;
    }
}
