<?php

namespace App\Models;

use App\Models\Concerns\LogsSystemActivity;
use Illuminate\Database\Eloquent\Model;

class AstMain extends Model
{
    use LogsSystemActivity;

    protected $table = 'ast_main';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = static::nextId();
            }
        });
    }

    /** `AST` + 2-digit year + 2-digit month, e.g. `AST2608` for August 2026. */
    public const ID_PREFIX = 'AST';

    /** Running number per month, so one month holds at most 9999 assets. */
    public const ID_SEQUENCE_MAX = 9999;

    /**
     * Prefix for the month an ID is generated in — `AST2608`.
     *
     * Legacy rows use the older `AST00001` form; they never share a prefix with a
     * generated one, so the two formats coexist without colliding.
     */
    public static function idPrefix(): string
    {
        return self::ID_PREFIX.now()->format('ym');
    }

    /**
     * Next free PK, e.g. `AST26080001`.
     */
    public static function nextId(): string
    {
        return static::nextIds(1)[0];
    }

    /**
     * Reserve a block of consecutive IDs in one query — a per-row `max()` would mean
     * one round trip per imported asset.
     *
     * @return array<int, string>
     *
     * @throws \RuntimeException when the month's 9999 slots would be exhausted
     */
    public static function nextIds(int $count): array
    {
        $prefix = static::idPrefix();
        $max = static::query()->where('id', 'like', $prefix.'%')->max('id');
        $start = ($max ? (int) substr($max, strlen($prefix)) : 0) + 1;

        if ($start + $count - 1 > self::ID_SEQUENCE_MAX) {
            throw new \RuntimeException(sprintf(
                'Kuota ID bulan ini habis: terpakai %d dari %d, butuh %d lagi.',
                $start - 1,
                self::ID_SEQUENCE_MAX,
                $count
            ));
        }

        return array_map(
            fn (int $i) => $prefix.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            range($start, $start + $count - 1)
        );
    }

    protected $fillable = [
        'id', 'ast_type', 'ast_brand', 'ast_brandmodel', 'ast_prodyear',
        'ast_serial', 'ast_vendid', 'ast_username', 'ast_userreg',
        'ast_userloc', 'ast_userlocdet', 'ast_cond', 'ast_delvdate',
        'ast_purcdate', 'ast_stat', 'ast_pjctid', 'ast_docid', 'ast_misc',
    ];

    protected $casts = [
        'ast_delvdate' => 'date',
        'ast_purcdate' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(PjctMain::class, 'ast_pjctid', 'id');
    }

    public function condBadgeClass(): string
    {
        return match ($this->ast_cond) {
            'Excellence' => 'bg-emerald-100 text-emerald-700',
            'Good' => 'bg-teal-100 text-teal-700',
            'Fair' => 'bg-amber-100 text-amber-700',
            'Bad' => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-400',
        };
    }

    public function statBadgeClass(): string
    {
        return match ($this->ast_stat) {
            'Aktif' => 'bg-green-100 text-green-700',
            'Aktif-Sewa' => 'bg-sky-100 text-sky-700',
            'Aktif-SewaBeli' => 'bg-blue-100 text-blue-700',
            'Back Up' => 'bg-amber-100 text-amber-700',
            'Pinjam' => 'bg-violet-100 text-violet-700',
            'Non-Aktif' => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-400',
        };
    }
}
