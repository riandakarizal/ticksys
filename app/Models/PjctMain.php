<?php

namespace App\Models;

use App\Models\AstMain;
use App\Models\Concerns\LogsSystemActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class PjctMain extends Model
{
    use SoftDeletes, LogsSystemActivity;

    protected $table      = 'pjct_main';
    public    $incrementing = false;
    protected $keyType    = 'string';

    /** PK prefix — `PJ` + 4-digit running number, e.g. `PJ0111`. */
    public const ID_PREFIX = 'PJ';

    /** Beyond this the ID gains a fifth digit and `max(id)` stops sorting correctly. */
    public const ID_SEQUENCE_MAX = 9999;

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = static::nextId();
            }
        });

        static::created(function (self $model): void {
            Storage::disk('docfile')->makeDirectory($model->id);
        });
    }

    /**
     * Next free PK, e.g. `PJ0112`.
     */
    public static function nextId(): string
    {
        return static::nextIds(1)[0];
    }

    /**
     * Reserve a block of consecutive IDs in one query, for bulk inserts that bypass the
     * `creating` hook. Soft-deleted rows count — reusing an archived project's ID would
     * resurrect it under a new project's name.
     *
     * @return array<int, string>
     *
     * @throws \RuntimeException when the `PJ9999` ceiling would be crossed
     */
    public static function nextIds(int $count): array
    {
        $max = static::withTrashed()->max('id'); // e.g. 'PJ0111'
        $start = ($max ? (int) substr($max, strlen(self::ID_PREFIX)) : 0) + 1;

        if ($start + $count - 1 > self::ID_SEQUENCE_MAX) {
            throw new \RuntimeException(sprintf(
                'Kuota ID project habis: terpakai %d dari %d, butuh %d lagi.',
                $start - 1,
                self::ID_SEQUENCE_MAX,
                $count
            ));
        }

        return array_map(
            fn (int $i) => self::ID_PREFIX.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            range($start, $start + $count - 1)
        );
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

    public function docs()
    {
        return $this->hasMany(PjctDoc::class, 'doc_pjctid', 'id');
    }

    public function budgets()
    {
        return $this->hasMany(PjctBudget::class, 'bdg_pjctid', 'id');
    }

    public function statusLabel(): string
    {
        return match ($this->pjct_status) {
            'UPC' => 'Upcoming',
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
            'UPC' => 'bg-purple-100 text-purple-700',
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
