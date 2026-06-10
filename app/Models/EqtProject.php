<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EqtProject extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type', 'tahun', 'nama', 'lob', 'pemberi_kerja', 'area', 'mitra',
        'nilai_pekerjaan', 'nilai_mitra', 'serapan',
        'start_date', 'end_date', 'no_kontrak', 'docs', 'status', 'keterangan',
    ];

    protected $casts = [
        'docs' => 'array',
        'nilai_pekerjaan' => 'integer',
        'nilai_mitra' => 'integer',
        'serapan' => 'integer',
    ];

    public function docScore(): array
    {
        $docs = $this->docs ?? [];
        if (empty($docs) || collect($docs)->every(fn($d) => $d === -1)) {
            return ['score' => -1, 'ok' => 0, 'total' => 0];
        }
        $valid = collect($docs)->filter(fn($d) => $d !== -1);
        $ok = $valid->filter(fn($d) => $d === 1)->count();
        $total = $valid->count();
        return ['score' => $total ? $ok / $total : 0, 'ok' => $ok, 'total' => $total];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'ON GOING'    => 'On Going',
            'PENDING'     => 'Pending',
            'NO KONTRAK'  => 'No Contract',
            'OUTSTANDING' => 'Outstanding',
            default       => $this->status ?? '',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'ON GOING'    => 'bg-green-100 text-green-800',
            'PENDING'     => 'bg-orange-100 text-orange-800',
            'NO KONTRAK'  => 'bg-red-100 text-red-800',
            'OUTSTANDING' => 'bg-rose-100 text-rose-900',
            default       => 'bg-slate-100 text-slate-600',
        };
    }

    public function lobBadgeClass(): string
    {
        return match (strtoupper($this->lob ?? '')) {
            'RENTAL'      => 'bg-blue-100 text-blue-800',
            'MAINTENANCE' => 'bg-red-100 text-red-800',
            'SUPPLIES', 'SUPPLY' => 'bg-green-100 text-green-800',
            'OM'          => 'bg-purple-100 text-purple-800',
            default       => 'bg-slate-100 text-slate-500',
        };
    }

    public static function docLabels(string $type): array
    {
        return $type === 'EQ'
            ? ['HOA/SPK', 'KAK/RKST', 'RAB/BOQ', 'JUSTIFIKASI', 'PO', 'BAST', 'BA-SAT']
            : ['HOA/SPK', 'KAK/RKST', 'RAB', 'SOP'];
    }
}
