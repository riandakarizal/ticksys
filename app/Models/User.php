<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable([
    'id',
    'user_empid',
    'user_name',
    'user_email',
    'user_pass',
    'user_level',
    'user_role',
    'user_unit',
    'user_div',
    'user_parid',
    'user_status',
])]
#[Hidden(['user_pass'])]
class User extends Model implements AuthenticatableContract
{
    use Authenticatable, HasFactory, Notifiable;

    public $incrementing = false;
    public $timestamps   = false;
    protected $keyType   = 'string';

    protected function casts(): array
    {
        return [];
    }

    // ── Auth overrides ────────────────────────────────────────────────────────
    public function getAuthIdentifierName(): string  { return 'id'; }
    public function getAuthIdentifier(): mixed        { return $this->id; }
    public function getAuthPassword(): string         { return $this->user_pass ?? ''; }
    public function getAuthPasswordName(): string     { return 'password'; }
    public function getRememberToken(): string        { return ''; }
    public function setRememberToken($value): void   {}
    public function getRememberTokenName(): string    { return ''; }

    // Maps virtual 'password' key → actual 'user_pass' column for rehash support
    protected function password(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->user_pass ?? '',
            set: fn (string $value) => ['user_pass' => $value],
        );
    }

    // ── Relationships ─────────────────────────────────────────────────────────
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    public function createdTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'created_by');
    }

    public function requestedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function notificationsFeed(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    // ── Role helpers ──────────────────────────────────────────────────────────
    public function jabatan(): string
    {
        return match ($this->user_level) {
            'L1'    => 'Direktur',
            'L2'    => 'Group Head',
            'L3'    => 'Division Head',
            'L4'    => 'Analyst',
            'L5'    => 'Senior Officer',
            'L6'    => 'Officer',
            'L7'    => 'Staff',
            default => $this->user_level ?? '-',
        };
    }

    public function roleLabel(): string
    {
        return match ($this->user_role) {
            'superadmin' => 'Super Admin',
            'admin'      => 'Admin',
            'siteadmin'  => 'Site Admin',
            'user'       => 'User',
            'vip'        => 'VIP',
            default      => $this->user_role ?? '-',
        };
    }

    public function isSuperAdmin(): bool { return $this->user_role === 'superadmin'; }
    public function isAdmin(): bool      { return $this->user_role === 'admin'; }
    public function isSiteAdmin(): bool  { return $this->user_role === 'siteadmin'; }
    public function isUser(): bool       { return $this->user_role === 'user'; }
    public function isVip(): bool        { return $this->user_role === 'vip'; }
    public function isActive(): bool     { return $this->user_status === 'active'; }

    public function canManageAllTickets(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canViewReports(): bool
    {
        return $this->isSuperAdmin() || $this->isAdmin() || $this->isVip();
    }

    // ── Division-based project visibility ──────────────────────────────────────
    private const UNIT_DIV_MAP = [
        'Technology Operation & Maintenance' => ['TC'],
        'Equipment Operation & Maintenance'  => ['EQ', 'EQREG1', 'EQREG2', 'EQREG3'],
        'Technology Commercial'              => ['TCC', 'TC'],
        'Equipment Commercial'               => ['EQC', 'EQ', 'EQREG1', 'EQREG2', 'EQREG3'],
    ];

    // Returns null = no filter (sees all), array = whitelist of pjct_div codes
    public function allowedDivCodes(): ?array
    {
        if ($this->isVip() || $this->isSuperAdmin()) {
            return null;
        }

        $codes = collect();

        if ($this->user_unit && isset(self::UNIT_DIV_MAP[$this->user_unit])) {
            $codes = $codes->merge(self::UNIT_DIV_MAP[$this->user_unit]);
        }

        $codes = $codes->merge($this->subordinateDivCodes($this->id));

        $result = $codes->unique()->values()->all();

        return empty($result) ? null : $result;
    }

    private function subordinateDivCodes(string $userId): Collection
    {
        $codes = collect();
        $subs  = static::where('user_parid', $userId)->get();

        foreach ($subs as $sub) {
            if ($sub->user_unit && isset(self::UNIT_DIV_MAP[$sub->user_unit])) {
                $codes = $codes->merge(self::UNIT_DIV_MAP[$sub->user_unit]);
            }
            $codes = $codes->merge($sub->subordinateDivCodes($sub->id));
        }

        return $codes;
    }
}
