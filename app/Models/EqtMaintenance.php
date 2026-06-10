<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EqtMaintenance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nama_alat', 'area', 'mitra', 'no_kontrak', 'tipe_alat',
        'last_service', 'next_service', 'status_maint', 'keterangan',
    ];

    public function statusBadgeClass(): string
    {
        return match ($this->status_maint) {
            'ON GOING'    => 'bg-green-100 text-green-800',
            'OUTSTANDING' => 'bg-rose-100 text-rose-900',
            'SELESAI'     => 'bg-green-100 text-green-900',
            'PENDING'     => 'bg-orange-100 text-orange-800',
            'BELUM'       => 'bg-red-100 text-red-800',
            default       => 'bg-slate-100 text-slate-600',
        };
    }
}
