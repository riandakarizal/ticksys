<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EqtHandover extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tahun', 'nama', 'lob', 'pemberi_kerja', 'area', 'mitra', 'status', 'keterangan',
    ];

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'PIPELINE' => 'bg-purple-100 text-purple-800',
            'ON GOING' => 'bg-green-100 text-green-800',
            'SELESAI'  => 'bg-green-100 text-green-900',
            default    => 'bg-slate-100 text-slate-600',
        };
    }

    public function lobBadgeClass(): string
    {
        return match (strtoupper($this->lob ?? '')) {
            'RENTAL'  => 'bg-blue-100 text-blue-800',
            'SUPPLY', 'SUPPLIES' => 'bg-green-100 text-green-800',
            default   => 'bg-slate-100 text-slate-500',
        };
    }
}
