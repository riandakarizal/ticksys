<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PjctBudget extends Model
{
    protected $table        = 'pjct_budget';
    public    $incrementing = false;
    protected $keyType      = 'string';
    public    $timestamps   = false;

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $prefix = 'BDG' . now()->format('ym'); // e.g. 'BDG2607'
                $max    = static::where('id', 'like', $prefix . '%')->max('id');
                $num    = $max ? ((int) substr($max, strlen($prefix))) + 1 : 1;
                $model->id = $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    protected $fillable = [
        'bdg_pjctid', 'bdg_name', 'bdg_type', 'bdg_type2', 'bdg_value',
    ];

    protected $casts = [
        'bdg_value' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(PjctMain::class, 'bdg_pjctid', 'id');
    }

    public function typeLabel(): string
    {
        return match ($this->bdg_type) {
            'PENGADAAN' => 'Pengadaan',
            'PEKERJAAN' => 'Pekerjaan',
            default     => $this->bdg_type ?? '-',
        };
    }
}
