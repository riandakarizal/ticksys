<?php

namespace App\Models;

use App\Models\Concerns\LogsSystemActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AstMain extends Model
{
    use LogsSystemActivity;

    protected $table        = 'ast_main';
    public    $timestamps   = false;
    public    $incrementing = false;
    protected $keyType      = 'string';

    protected $fillable = [
        'id', 'ast_type', 'ast_brand', 'ast_brandmodel', 'ast_prodyear',
        'ast_serial', 'ast_vendid', 'ast_username', 'ast_userreg',
        'ast_userloc', 'ast_userlocdet', 'ast_cond', 'ast_delvdate',
        'ast_purcdate', 'ast_stat', 'ast_pjctid', 'ast_docid', 'ast_misc',
        'ast_last_ticket_id',
    ];

    protected $casts = [
        'ast_delvdate' => 'date',
        'ast_purcdate' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $max = static::max('id'); // e.g. 'AST03583'
                $num = $max ? ((int) substr($max, 3)) + 1 : 1;
                $model->id = 'AST' . str_pad((string) $num, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    public function project()
    {
        return $this->belongsTo(PjctMain::class, 'ast_pjctid', 'id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'ast_id', 'id');
    }

    public function lastTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ast_last_ticket_id');
    }

    public function hasOpenTicket(): bool
    {
        return $this->ast_last_ticket_id !== null;
    }

    public function condBadgeClass(): string
    {
        return match ($this->ast_cond) {
            'Excellence' => 'bg-emerald-100 text-emerald-700',
            'Good'       => 'bg-teal-100 text-teal-700',
            'Fair'       => 'bg-amber-100 text-amber-700',
            'Bad'        => 'bg-red-100 text-red-700',
            default      => 'bg-slate-100 text-slate-400',
        };
    }

    public function statBadgeClass(): string
    {
        return match ($this->ast_stat) {
            'Aktif'          => 'bg-green-100 text-green-700',
            'Aktif-Sewa'     => 'bg-sky-100 text-sky-700',
            'Aktif-SewaBeli' => 'bg-blue-100 text-blue-700',
            'Back Up'        => 'bg-amber-100 text-amber-700',
            'Pinjam'         => 'bg-violet-100 text-violet-700',
            'Non-Aktif'      => 'bg-red-100 text-red-700',
            default          => 'bg-slate-100 text-slate-400',
        };
    }
}
