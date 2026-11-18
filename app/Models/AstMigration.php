<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu batch migrasi aset dari satu project ke project lain. Dibuat hanya lewat
 * `AssetMigrationService::migrate()`.
 */
class AstMigration extends Model
{
    protected $table = 'ast_migrations';

    protected $fillable = [
        'from_pjct_id', 'to_pjct_id', 'user_id', 'asset_count',
        'stat_after', 'cleared_location', 'note',
    ];

    protected $casts = [
        'asset_count' => 'integer',
        'cleared_location' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(AstMigrationItem::class, 'ast_migration_id');
    }

    public function fromProject(): BelongsTo
    {
        return $this->belongsTo(PjctMain::class, 'from_pjct_id')->withTrashed();
    }

    public function toProject(): BelongsTo
    {
        return $this->belongsTo(PjctMain::class, 'to_pjct_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
