<?php

namespace App\Models;

use App\Models\Concerns\LogsSystemActivity;
use Illuminate\Database\Eloquent\Model;

class PjctEmp extends Model
{
    use LogsSystemActivity;

    protected $table = 'pjct_emp';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /** PK prefix — `EM` + 5-digit running number, e.g. `EM00045`. */
    public const ID_PREFIX = 'EM';

    /** Beyond this the ID gains a sixth digit and `max(id)` stops sorting correctly. */
    public const ID_SEQUENCE_MAX = 99999;

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = static::nextIds(1)[0];
            }
        });
    }

    /**
     * Reserve a block of consecutive IDs in one query, for bulk inserts that bypass the
     * `creating` hook.
     *
     * @return array<int, string>
     *
     * @throws \RuntimeException when the `EM99999` ceiling would be crossed
     */
    public static function nextIds(int $count): array
    {
        $max = static::query()->where('id', 'like', self::ID_PREFIX.'%')->max('id');
        $start = ($max ? (int) substr($max, strlen(self::ID_PREFIX)) : 0) + 1;

        if ($start + $count - 1 > self::ID_SEQUENCE_MAX) {
            throw new \RuntimeException(sprintf(
                'Kuota ID manpower habis: terpakai %d dari %d, butuh %d lagi.',
                $start - 1,
                self::ID_SEQUENCE_MAX,
                $count
            ));
        }

        return array_map(
            fn (int $i) => self::ID_PREFIX.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
            range($start, $start + $count - 1)
        );
    }

    /**
     * ID project (`pjct_main.id`) tempat NIK ini ditugaskan.
     *
     * `emp_pjctid` berisi ID project (`PJxxxx`) untuk data baru, atau nomor kontrak terpotong
     * 20 karakter untuk data lama — keduanya dicocokkan, sama seperti halaman Manpower. Tidak
     * pakai JOIN: `pjct_emp` dan `pjct_main` beda collation di MySQL.
     *
     * @return array<int, string>
     */
    public static function projectIdsForNik(?string $nik): array
    {
        $nik = trim((string) $nik);
        if ($nik === '') {
            return [];
        }

        $keys = static::query()->where('emp_id', $nik)->pluck('emp_pjctid')
            ->map(fn ($key) => trim((string) $key))->filter()->unique()->values();
        if ($keys->isEmpty()) {
            return [];
        }

        $direct = PjctMain::withTrashed()->whereIn('id', $keys->all())->pluck('id');
        $contractKeys = $keys->diff($direct)->values();

        // LIKE hanya menyaring kandidat; kecocokan pastinya (20 karakter pertama) dicek di PHP.
        $byContract = $contractKeys->isEmpty() ? collect() : PjctMain::withTrashed()
            ->where(function ($query) use ($contractKeys): void {
                foreach ($contractKeys as $key) {
                    $query->orWhere('pjct_contract', 'like', '%'.$key.'%');
                }
            })
            ->get(['id', 'pjct_contract'])
            ->filter(fn (PjctMain $project) => $contractKeys->contains(mb_substr(trim((string) $project->pjct_contract), 0, 20)))
            ->pluck('id');

        return $direct->merge($byContract)->unique()->values()->all();
    }

    protected $fillable = [
        'id', 'emp_id', 'emp_name', 'emp_level', 'emp_levname',
        'emp_unit', 'emp_div', 'emp_area', 'emp_pjctid',
        'emp_coid', 'emp_costart', 'emp_coend', 'emp_contact', 'emp_misc',
    ];

    protected $casts = [
        'emp_costart' => 'date',
        'emp_coend' => 'date',
    ];
}
