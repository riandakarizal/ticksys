<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Carbon\CarbonInterface;

class Ticket extends Model
{
    public const STATUSES = ['open', 'in_progress', 'pending', 'resolved', 'closed'];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    protected $fillable = [
        'tenant_id',
        'requester_id',
        'created_by',
        'assigned_to',
        'team_id',
        'device_id',
        'category_id',
        'subcategory_id',
        'sla_policy_id',
        'merged_into_ticket_id',
        'split_from_ticket_id',
        'ticket_number',
        'subject',
        'description',
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
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket): void {
            if (! $ticket->ticket_number) {
                $prefix = 'HD-'.now()->format('Ymd');
                $sequence = static::query()
                    ->where('ticket_number', 'like', $prefix.'-%')
                    ->count() + 1;

                $ticket->ticket_number = $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
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
        return $this->hasMany(TicketMessage::class)->latest();
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

    public function isResponseBreached(): bool
    {
        return $this->response_due_at instanceof Carbon && $this->response_due_at->isPast();
    }

    public function isResolutionBreached(): bool
    {
        return $this->resolution_due_at instanceof Carbon
            && $this->resolution_due_at->isPast();
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function responseDueClass(): string
    {
        if (! ($this->response_due_at instanceof Carbon)) {
            return 'text-slate-900';
        }

        return $this->isResponseBreached()
            ? 'text-rose-600 font-semibold'
            : 'text-emerald-600 font-semibold';
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
            'in_progress' => 'bg-amber-100 text-amber-700',
            'pending' => 'bg-orange-100 text-orange-700',
            'resolved' => 'bg-emerald-100 text-emerald-700',
            'closed' => 'bg-slate-200 text-slate-700',
            default => 'bg-slate-100 text-slate-700',
        };
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
        if ($this->isClosed()) {
            return 'bg-emerald-100 text-emerald-700';
        }

        if (! ($this->resolution_due_at instanceof Carbon)) {
            return null;
        }

        return $this->isResolutionBreached()
            ? 'bg-rose-100 text-rose-700'
            : 'bg-amber-100 text-amber-700';
    }

    public function slaBadgeLabel(): ?string
    {
        if ($this->isClosed()) {
            return 'On Track';
        }

        if (! ($this->resolution_due_at instanceof Carbon)) {
            return null;
        }

        if ($this->isResolutionBreached()) {
            return 'Breached';
        }

        return $this->resolution_due_at->diffForHumans(now(), CarbonInterface::DIFF_ABSOLUTE, false, 1);
    }
}
