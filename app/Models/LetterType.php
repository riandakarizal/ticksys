<?php

namespace App\Models;

use App\Models\Concerns\LogsSystemActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jenis surat (master). `code` adalah kunci urutan nomor: jenis yang kodenya sama berbagi urutan.
 */
class LetterType extends Model
{
    use LogsSystemActivity;

    protected $fillable = ['name', 'code', 'unit_code', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Contoh nomor untuk ditampilkan di form, mis. `IASS/DEO/SKR/XI/2026/001`. */
    public function sampleNumber(): string
    {
        return Letter::formatNumber($this->unit_code, $this->code, (int) now()->month, (int) now()->year, 1);
    }
}
