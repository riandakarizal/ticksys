<?php

namespace App\Models;

use App\Models\Concerns\LogsSystemActivity;
use Illuminate\Database\Eloquent\Model;

class AstMain extends Model
{
    use LogsSystemActivity;

    protected $table      = 'ast_main';
    public    $timestamps = false;

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
