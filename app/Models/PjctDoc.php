<?php

namespace App\Models;

use App\Models\Concerns\LogsSystemActivity;
use Illuminate\Database\Eloquent\Model;

class PjctDoc extends Model
{
    use LogsSystemActivity;

    protected $table      = 'pjct_doc';
    public    $incrementing = false;
    protected $keyType    = 'string';
    public    $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $max = static::max('id'); // e.g. 'DOC00065'
                $num = $max ? ((int) substr($max, 3)) + 1 : 1;
                $model->id = 'DOC' . str_pad($num, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    /**
     * Jenis dokumen yang bisa diunggah: kode (disimpan di `doc_type`) → label dropdown.
     * Kode lama (KONTRAK/RKST/RAB/BAST/BOQ) dipertahankan agar dokumen yang sudah ada tetap terbaca.
     */
    public const TYPES = [
        'KONTRAK' => 'Kontrak',
        'RAB'     => 'RAB',
        'PNL'     => 'PNL',
        'RKST'    => 'RKST/KAK',
        'BOQ'     => 'BoQ',
        'BAK'     => 'Berita Acara Kesepakatan (BAK)',
        'BAST'    => 'Berita Acara Serah Terima (BAST)',
        'BASTO'   => 'Berita Acara Serah Terima Operasi (BASTO)',
        'BAPP'    => 'Berita Acara Penyelesaian Pekerjaan (BAPP)',
    ];

    protected $fillable = [
        'doc_number', 'doc_pjctid', 'doc_type', 'doc_filetype',
        'doc_filename', 'doc_filepath', 'doc_desc',
    ];

    public function project()
    {
        return $this->belongsTo(PjctMain::class, 'doc_pjctid', 'id');
    }

    public function typeBadgeClass(): string
    {
        return static::badgeClassFor($this->doc_type);
    }

    public static function badgeClassFor(?string $type): string
    {
        return match ($type) {
            'KONTRAK' => 'bg-blue-100 text-blue-700',
            'RKST'    => 'bg-violet-100 text-violet-700',
            'RAB'     => 'bg-teal-100 text-teal-700',
            'PNL'     => 'bg-cyan-100 text-cyan-700',
            'BOQ'     => 'bg-rose-100 text-rose-700',
            'BAK'     => 'bg-indigo-100 text-indigo-700',
            'BAST'    => 'bg-green-100 text-green-700',
            'BASTO'   => 'bg-emerald-100 text-emerald-700',
            'BAPP'    => 'bg-lime-100 text-lime-700',
            'SOP'     => 'bg-amber-100 text-amber-700',   // jenis lama, tidak bisa diunggah lagi
            default   => 'bg-slate-100 text-slate-500',
        };
    }
}
