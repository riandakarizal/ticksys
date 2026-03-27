<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\CustomField;
use App\Models\SlaPolicy;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCustomFieldValue;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Helpdesk
{
    public function visibleTickets(User $user): Builder
    {
        $query = Ticket::query()->where('tickets.tenant_id', $user->tenant_id);

        if ($user->canManageAllTickets()) {
            return $query;
        }

        $projectIds = $user->teams()->pluck('teams.id');

        if ($user->isCoordinator()) {
            return $query->whereIn('tickets.team_id', $projectIds);
        }

        if ($user->isAgent()) {
            return $query
                ->whereIn('tickets.team_id', $projectIds)
                ->where('tickets.assigned_to', $user->id);
        }

        return $query
            ->whereIn('tickets.team_id', $projectIds)
            ->where('tickets.requester_id', $user->id);
    }

    public function visibleProjects(User $user): Builder
    {
        $query = Team::query()->where('teams.tenant_id', $user->tenant_id);

        if ($user->canManageAllTickets()) {
            return $query;
        }

        return $query->whereHas('members', fn (Builder $builder) => $builder->where('users.id', $user->id));
    }

    public function parseTags(?string $tags): array
    {
        if (! $tags) {
            return [];
        }

        return collect(explode(',', $tags))
            ->map(fn (string $tag) => Str::of($tag)->trim()->lower()->toString())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function autoAssignUserForProject(Team $project, ?int $preferredUserId = null): ?int
    {
        $members = $project->members;

        if ($preferredUserId && $members->contains('id', $preferredUserId)) {
            return $preferredUserId;
        }

        $agent = $members->firstWhere('role', 'agent');
        if ($agent) {
            return $agent->id;
        }

        $supervisor = $members->firstWhere('role', 'supervisor');
        if ($supervisor) {
            return $supervisor->id;
        }

        $admin = $members->firstWhere('role', 'admin');

        return $admin?->id;
    }

    public function applySlaDeadlines(Ticket $ticket, ?SlaPolicy $policy = null): void
    {
        $policy ??= $ticket->slaPolicy;

        if (! $policy) {
            return;
        }

        $startedAt = now();
        $ticket->response_due_at = $startedAt->copy()->addMinutes($policy->response_minutes);
        $ticket->resolution_due_at = $startedAt->copy()->addMinutes($policy->resolution_minutes);
    }

    public function syncCustomFields(Ticket $ticket, Collection $fields, array $values): void
    {
        foreach ($fields as $field) {
            $value = $values[$field->key] ?? null;

            TicketCustomFieldValue::updateOrCreate(
                ['ticket_id' => $ticket->id, 'custom_field_id' => $field->id],
                ['value' => is_array($value) ? json_encode($value) : $value],
            );
        }
    }

    public function storeAttachments(Ticket $ticket, array $files, ?int $userId = null, ?int $messageId = null): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $ticket->attachments()->create([
                'ticket_message_id' => $messageId,
                'user_id' => $userId,
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store('ticket-attachments'),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
            ]);
        }
    }

    public function recordActivity(?Ticket $ticket, ?User $user, string $action, string $description, array $properties = []): void
    {
        ActivityLog::create([
            'tenant_id' => $ticket?->tenant_id ?? $user?->tenant_id,
            'ticket_id' => $ticket?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'description' => $description,
            'properties' => $properties,
        ]);
    }

    public function notifyUsers(iterable $users, ?Ticket $ticket, string $type, string $title, string $message, array $data = []): void
    {
        $recipients = collect($users)
            ->filter(fn ($user) => $user instanceof User)
            ->unique('id');

        foreach ($recipients as $user) {
            AppNotification::create([
                'user_id' => $user->id,
                'ticket_id' => $ticket?->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
            ]);

            if ($user->email) {
                Mail::raw($title.PHP_EOL.PHP_EOL.$message, function ($mail) use ($user, $title): void {
                    $mail->to($user->email)->subject($title);
                });
            }
        }
    }

    public function participants(Ticket $ticket): Collection
    {
        return collect([$ticket->requester, $ticket->assignee])->filter();
    }

    public function extractMentionedUsers(string $body, User $actor): Collection
    {
        preg_match_all('/@([A-Za-z0-9._-]+)/', $body, $matches);

        $needles = collect($matches[1] ?? [])->map(fn (string $value) => Str::lower($value))->unique();

        if ($needles->isEmpty()) {
            return collect();
        }

        return User::query()
            ->where('tenant_id', $actor->tenant_id)
            ->get()
            ->filter(function (User $user) use ($needles): bool {
                $emailLocal = Str::before(Str::lower($user->email), '@');
                $nameSlug = Str::slug($user->name, '');

                return $needles->contains($emailLocal) || $needles->contains($nameSlug);
            })
            ->values();
    }

    public function defaultCustomFields(User $user): Collection
    {
        return CustomField::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function attachmentPathExists(string $path): bool
    {
        return Storage::exists($path);
    }
}
