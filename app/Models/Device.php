<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    public const STATUS_ACTIVE = 'Active';

    public const STATUS_INACTIVE = 'Inactive';

    public const STATUS_UNDER_REPARATION = 'Under Reparation';

    protected $fillable = [
        'tenant_id',
        'team_id',
        'name',
        'asset_code',
        'device_type',
        'serial_number',
        'ip_address',
        'location',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function hasOpenRepairTicket(): bool
    {
        if (array_key_exists('has_open_ticket', $this->attributes)) {
            return (bool) $this->attributes['has_open_ticket'];
        }

        return $this->tickets()
            ->where('status', '!=', 'closed')
            ->exists();
    }

    public function operationalStatusLabel(): string
    {
        if ($this->hasOpenRepairTicket()) {
            return self::STATUS_UNDER_REPARATION;
        }

        return $this->is_active ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;
    }

    public function operationalStatusBadgeClass(): string
    {
        return match ($this->operationalStatusLabel()) {
            self::STATUS_UNDER_REPARATION => 'bg-amber-100 text-amber-700',
            self::STATUS_ACTIVE => 'bg-emerald-100 text-emerald-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}
