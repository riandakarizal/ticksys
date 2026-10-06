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
