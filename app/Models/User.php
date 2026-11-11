<?php

namespace App\Models;

use App\Models\Concerns\LogsSystemActivity;
use Database\Factories\UserFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
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
class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, HasFactory, Notifiable, LogsSystemActivity;

    public $incrementing = false;
    public $timestamps   = false;
    protected $keyType   = 'string';

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $max = static::max('id'); // e.g. 'USR-012'
                $num = $max ? ((int) substr($max, 4)) + 1 : 1;
                $model->id = 'USR-' . str_pad($num, 3, '0', STR_PAD_LEFT);
            }
        });
    }

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
            'fin'        => 'Finance',
            default      => $this->user_role ?? '-',
        };
    }

    public function isSuperAdmin(): bool { return $this->user_role === 'superadmin'; }
    public function isAdmin(): bool      { return $this->user_role === 'admin'; }
    public function isSiteAdmin(): bool  { return $this->user_role === 'siteadmin'; }
    public function isUser(): bool       { return $this->user_role === 'user'; }
    public function isVip(): bool        { return $this->user_role === 'vip'; }
    // Finance — akses daftar-putih: Dashboard, Project → Asset, Report → Data.
    // Route lain ditutup di routes/web.php, menunya disembunyikan di layouts/app.blade.php.
    public function isFin(): bool        { return $this->user_role === 'fin'; }
    public function isActive(): bool     { return $this->user_status === 'active'; }

    public function canManageAllTickets(): bool
    {
        return $this->isSuperAdmin();
    }

    // ── Ticketing ──────────────────────────────────────────────────────────────
    // Teknisi lapangan = role `user`: membuat tiket, eskalasi, menandai solved.
    // Atasan = user_parid teknisi pembuat tiket: approve/tolak solved.
    // Tim divisi project = admin/superadmin yang divisinya mencakup pjct_div: menurunkan escalated.
    public function isTicketTechnician(): bool
    {
        return $this->isUser();
    }

    /** Divisi project masuk cakupan user ini (allowedDivCodes() null = semua divisi). */
    public function coversDivision(?string $divCode): bool
    {
        $codes = $this->allowedDivCodes();

        return $codes === null || in_array($divCode, $codes, true);
    }

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'user_parid');
    }

    /**
     * Import Excel aset (tombol + template di Project → Asset): superadmin, dan admin divisi
     * Equipment & Technology Operation & Maintenance. Non-superadmin hanya boleh mengimpor ke
     * project dalam allowedDivCodes() — dibatasi di AssetImportService.
     */
    public function canImportAssets(): bool
    {
        return $this->isSuperAdmin()
            || ($this->isAdmin() && $this->user_div === 'Equipment & Technology Operation & Maintenance');
    }

    public function canViewReports(): bool
    {
        return $this->isSuperAdmin() || $this->isAdmin() || $this->isVip();
    }

    // ── Project assignment (pjct_emp) ──────────────────────────────────────────
    /** @var array<int, string>|null */
    private ?array $assignedProjectIdsCache = null;

    /**
     * Teknisi (role `user`) hanya melihat project tempat ia ditugaskan di `pjct_emp`, dicocokkan
     * lewat NIK (`users.user_empid` = `pjct_emp.emp_id`). Penugasan ini menggantikan filter
     * divisi untuknya. Role lain → null (tidak dibatasi per project, tetap lewat allowedDivCodes()).
     *
     * @return array<int, string>|null  [] = belum ditugaskan di project mana pun → tidak melihat apa pun
     */
    public function assignedProjectIds(): ?array
    {
        if (! $this->isUser()) {
            return null;
        }

        return $this->assignedProjectIdsCache ??= PjctEmp::projectIdsForNik($this->user_empid);
    }

    /**
     * ID project (termasuk yang diarsipkan) yang boleh dilihat, atau null = semua project.
     * Untuk menyaring tabel lain lewat whereIn — `pjct_main` beda collation dengan tabel legacy
     * lain, jadi JOIN/whereHas ke sana bisa ditolak MySQL.
     *
     * @return array<int, string>|null
     */
    public function visibleProjectIds(): ?array
    {
        if (($assigned = $this->assignedProjectIds()) !== null) {
            return $assigned;
        }

        $divs = $this->allowedDivCodes();

        return $divs === null ? null : PjctMain::withTrashed()->whereIn('pjct_div', $divs)->pluck('id')->all();
    }

    /** Project ini boleh dilihat user — penugasan pjct_emp (teknisi) atau divisi (role lain). */
    public function canSeeProject(?PjctMain $project): bool
    {
        if (($assigned = $this->assignedProjectIds()) !== null) {
            return $project !== null && in_array($project->id, $assigned, true);
        }

        $divs = $this->allowedDivCodes();

        return $divs === null || ($project !== null && in_array($project->pjct_div, $divs, true));
    }

    // ── Division-based project visibility ──────────────────────────────────────
    private const UNIT_DIV_MAP = [
        'Technology Operation & Maintenance' => ['TC'],
        'Equipment Operation & Maintenance'  => ['EQ', 'EQREG1', 'EQREG2', 'EQREG3'],
        'Technology Commercial'              => ['TC', 'TCREG1', 'TCREG2'],
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
