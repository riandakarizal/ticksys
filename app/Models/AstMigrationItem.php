<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AstMigrationItem extends Model
{
    protected $table = 'ast_migration_items';

    public $timestamps = false;

    protected $fillable = ['ast_migration_id', 'ast_id', 'before'];

    /** Snapshot sebelum dipindah: ast_stat, ast_username, ast_userreg, ast_userloc, ast_userlocdet. */
    protected $casts = [
        'before' => 'array',
    ];

    public function migration(): BelongsTo
    {
        return $this->belongsTo(AstMigration::class, 'ast_migration_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AstMain::class, 'ast_id');
    }
}
