<?php

namespace App\Models;

use App\Models\AstMain;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PjctMain extends Model
{
    use SoftDeletes;

    protected $table      = 'pjct_main';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $max = static::withTrashed()->max('id'); // e.g. 'PJ0066'
                $num = $max ? ((int) substr($max, 2)) + 1 : 1;
                $model->id = 'PJ' . str_pad($num, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    protected $fillable = [
        'pjct_contract', 'pjct_codate', 'pjct_div', 'pjct_name',
        'pjct_type', 'pjct_client', 'pjct_area', 'pjct_value',
        'pjct_budgetid', 'pjct_costart', 'pjct_totalperiod',
        'pjct_coend_m', 'pjct_status', 'pjct_misc',
    ];

    protected $casts = [
        'pjct_codate'  => 'date',
        'pjct_costart' => 'date',
        'pjct_coend_m' => 'date',
        'pjct_value'   => 'integer',
    ];

    public function assets()
    {
        return $this->hasMany(AstMain::class, 'ast_pjctid', 'id');
    }

    public function statusLabel(): string
    {
        return match ($this->pjct_status) {
            'OG'  => 'On Going',
            'HVR' => 'Hand Over',
            'DLY' => 'Delay',
            'END' => 'Ended',
            default => $this->pjct_status ?? '-',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->pjct_status) {
            'OG'  => 'bg-green-100 text-green-700',
            'HVR' => 'bg-blue-100 text-blue-700',
            'DLY' => 'bg-amber-100 text-amber-700',
            'END' => 'bg-slate-100 text-slate-500',
            default => 'bg-slate-100 text-slate-500',
        };
    }

    public function typeBadgeClass(): string
    {
        return match ($this->pjct_type) {
            'RENT'   => 'bg-violet-100 text-violet-700',
            'SUPPLY' => 'bg-sky-100 text-sky-700',
            'JASA'   => 'bg-teal-100 text-teal-700',
            default  => 'bg-slate-100 text-slate-500',
        };
    }
}
