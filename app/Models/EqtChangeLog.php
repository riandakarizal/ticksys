<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EqtChangeLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'record_type', 'record_id', 'record_label',
        'action', 'source', 'old_values', 'new_values', 'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('record_type', $type);
    }

    public function scopeByRecord($query, string $type, int $id)
    {
        return $query->where('record_type', $type)->where('record_id', $id);
    }

    public function scopeRecent($query, int $limit = 50)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    public static function record(
        ?User $user,
        string $recordType,
        int $recordId,
        string $recordLabel,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $source = 'manual'
    ): self {
        return static::create([
            'user_id'      => $user?->id,
            'record_type'  => $recordType,
            'record_id'    => $recordId,
            'record_label' => $recordLabel,
            'action'       => $action,
            'source'       => $source,
            'old_values'   => $oldValues,
            'new_values'   => $newValues,
            'created_at'   => now(),
        ]);
    }

    public function actionBadgeClass(): string
    {
        return match ($this->action) {
            'created'  => 'bg-green-100 text-green-800',
            'updated'  => 'bg-blue-100 text-blue-800',
            'archived' => 'bg-red-100 text-red-800',
            'restored' => 'bg-purple-100 text-purple-800',
            'imported' => 'bg-orange-100 text-orange-800',
            default    => 'bg-slate-100 text-slate-600',
        };
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'created'  => 'Dibuat',
            'updated'  => 'Diperbarui',
            'archived' => 'Diarsipkan',
            'restored' => 'Dipulihkan',
            'imported' => 'Diimport',
            default    => $this->action,
        };
    }

    public function recordTypeLabel(): string
    {
        return match ($this->record_type) {
            'project'     => 'Project',
            'handover'    => 'Hand Over',
            'vehicle'     => 'Kendaraan',
            'maintenance' => 'Maintenance',
            default       => $this->record_type,
        };
    }
}
