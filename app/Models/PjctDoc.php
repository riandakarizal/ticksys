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
            'BAST'    => 'bg-green-100 text-green-700',
            'SOP'     => 'bg-amber-100 text-amber-700',
            'BOQ'     => 'bg-rose-100 text-rose-700',
            default   => 'bg-slate-100 text-slate-500',
        };
    }
}
