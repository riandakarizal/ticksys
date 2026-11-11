<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tiket kerusakan/accident/masalah sistem pada project PRISM, dibuat oleh teknisi lapangan.
 *
 * Flow: open → solved (teknisi) → closed (approve atasan); solved bisa ditolak kembali ke open.
 *       open → escalated (teknisi, BA ke pusat) → open (tim divisi project, BA dari pusat).
 * `escalated` dihitung sebagai tiket aktif ("termasuk open").
 */
class Ticket extends Model
{
    use HasFactory;

    public const STATUSES = ['open', 'escalated', 'solved', 'closed'];

    /** Tiket yang masih dikerjakan — escalated termasuk open. */
    public const ACTIVE_STATUSES = ['open', 'escalated'];

    public const STATUS_LABELS = [
        'open' => 'Open',
        'escalated' => 'Escalated',
        'solved' => 'Solved',
        'closed' => 'Closed',
    ];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public const ISSUE_TYPES = [
        'kerusakan' => 'Kerusakan Device',
        'accident' => 'Accident',
        'sistem' => 'Masalah Sistem',
    ];

    /** Jenis masalah yang wajib menyertakan minimal satu device project. */
    public const ISSUE_TYPES_REQUIRING_DEVICE = ['kerusakan', 'accident'];

    protected $fillable = [
        'requester_id',
        'created_by',
        'assigned_to',
        'pjct_id',
        'requester_name',
        'reporter_name',
        'category_id',
        'subcategory_id',
        'sla_policy_id',
        'ticket_number',
        'subject',
        'description',
        'issue_type',
        'status',
        'priority',
        'tags',
        'metadata',
        'first_responded_at',
        'last_reply_at',
        'response_due_at',
        'resolution_due_at',
        'resolved_at',
        'closed_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'metadata' => 'array',
            'first_responded_at' => 'datetime',
            'last_reply_at' => 'datetime',
            'response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            // Waktu tiket ditandai solved oleh teknisi (nama kolom warisan flow lama).
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'auto_close_warned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket): void {
            if ($ticket->ticket_number) {
                return;
            }

            $date = now()->format('Ymd');

            $ticket->ticket_number = DB::transaction(function () use ($date): string {
                // Ensure the sequence row exists with a starting value of 0,
                // then lock the row and increment atomically within the transaction.
                DB::table('ticket_sequences')->insertOrIgnore([
                    'sequence_date' => $date,
                    'sequence' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $sequence = DB::table('ticket_sequences')
                    ->where('sequence_date', $date)
                    ->lockForUpdate()
                    ->value('sequence') + 1;

                DB::table('ticket_sequences')
                    ->where('sequence_date', $date)
                    ->update(['sequence' => $sequence, 'updated_at' => now()]);

                return 'HD-'.$date.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            });
        });
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** Teknisi yang membuat tiket — penanggung jawab penanganan. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PjctMain::class, 'pjct_id', 'id');
    }

    /** Device project yang terlibat; pivot cond_before = kondisi sebelum dilaporkan. */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(AstMain::class, 'ticket_assets', 'ticket_id', 'ast_id')
            ->withPivot('cond_before')
            ->withTimestamps();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(TicketCustomFieldValue::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /** Client = pemilik project PRISM; fallback ke data tiket lama. */
    public function clientLabel(): string
    {
        return $this->project?->pjct_client ?? $this->requester_name ?? $this->requester?->user_name ?? '-';
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function issueTypeLabel(): string
    {
        return self::ISSUE_TYPES[$this->issue_type] ?? '-';
    }

    public function requiresDevice(): bool
    {
        return in_array($this->issue_type, self::ISSUE_TYPES_REQUIRING_DEVICE, true);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isResponseBreached(): bool
    {
        if (! ($this->response_due_at instanceof Carbon)) {
            return false;
        }

        if ($this->first_responded_at) {
            return $this->first_responded_at->gt($this->response_due_at);
        }

        return $this->response_due_at->isPast();
    }

    public function isResolutionBreached(): bool
    {
        if (! ($this->resolution_due_at instanceof Carbon)) {
            return false;
        }

        // Waktu selesai = saat ditandai solved; tiket lama yang langsung closed memakai closed_at.
        $finishedAt = $this->resolved_at ?? $this->closed_at;

        if ($finishedAt) {
            return $finishedAt->gt($this->resolution_due_at);
        }

        return $this->resolution_due_at->isPast();
    }

    public function resolutionDueClass(): string
    {
        if (! ($this->resolution_due_at instanceof Carbon)) {
            return 'text-slate-900';
        }

        return $this->isResolutionBreached()
            ? 'text-rose-600 font-semibold'
            : 'text-emerald-600 font-semibold';
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'open' => 'bg-blue-100 text-blue-700',
            'escalated' => 'bg-violet-100 text-violet-700',
            'solved' => 'bg-emerald-100 text-emerald-700',
            'closed' => 'bg-slate-200 text-slate-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    public static function statusBadgeClassFor(?string $status): string
    {
        return (new self(['status' => $status]))->statusBadgeClass();
    }

    public function priorityBadgeClass(): string
    {
        return match ($this->priority) {
            'low' => 'bg-blue-100 text-blue-700',
            'medium' => 'bg-amber-100 text-amber-700',
            'high' => 'bg-orange-100 text-orange-700',
            'critical' => 'bg-rose-100 text-rose-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    public function slaBadgeClass(): ?string
    {
        if (! ($this->resolution_due_at instanceof Carbon)) {
            return null;
        }

        return $this->isResolutionBreached()
            ? 'bg-rose-100 text-rose-700'
            : 'bg-emerald-100 text-emerald-700';
    }

    public function slaBadgeLabel(): ?string
    {
        if (! ($this->resolution_due_at instanceof Carbon)) {
            return null;
        }

        if ($this->isResolutionBreached()) {
            return 'Breached';
        }

        if (! $this->isActive()) {
            return 'On Track';
        }

        return $this->resolution_due_at->diffForHumans(now(), CarbonInterface::DIFF_ABSOLUTE, false, 1);
    }

    // Tiket masih bisa berubah dari "on track" ke "breached" seiring waktu berjalan —
    // dipakai UI untuk tahu badge mana yang perlu di-countdown live di browser.
    public function isSlaCountdownLive(): bool
    {
        return $this->resolution_due_at instanceof Carbon
            && $this->isActive()
            && ! $this->isResolutionBreached();
    }
}
