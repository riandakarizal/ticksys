<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EqtVehicle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nopol', 'jenis', 'customer', 'vendor', 'no_kontrak',
        'pkb_date', 'nilai_pkb', 'status_pajak', 'keterangan',
    ];

    protected $casts = [
        'nilai_pkb' => 'integer',
    ];

    public function statusPajakBadgeClass(): string
    {
        return match ($this->status_pajak) {
            'EXPIRED' => 'bg-red-100 text-red-800',
            'SOON'    => 'bg-yellow-100 text-yellow-800',
            'OK'      => 'bg-green-100 text-green-800',
            default   => 'bg-slate-100 text-slate-500',
        };
    }
}
