<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Urutan terakhir per kode + tahun. Baris ini juga berfungsi sebagai kunci (lockForUpdate) saat
 * nomor diambil — lihat LetterNumberService.
 */
class LetterCounter extends Model
{
    protected $fillable = ['code', 'year', 'last_seq'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'last_seq' => 'integer'];
    }
}
