<?php

namespace App\Support;

use App\Mail\TicketEventMail;
use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\AstMain;
use App\Models\CustomField;
use App\Models\SlaPolicy;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCustomFieldValue;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Helpdesk
{
    public function visibleTickets(User $user): Builder
    {
        // Tiket tidak lagi terikat ke Team (lihat TicketManager::createTicket) — jadi
        // visibilitas staff tidak lagi disaring lewat keanggotaan team_user.
        if ($user->canManageAllTickets() || $user->isVip() || $user->isAdmin()) {
            return Ticket::query();
        }

        if ($user->isSiteAdmin()) {
            return Ticket::query()->where('tickets.assigned_to', $user->id);
        }

        return Ticket::query()->where('tickets.requester_id', $user->id);
    }

    public function visibleProjects(User $user): Builder
    {
        // Hanya ada segelintir support team di organisasi ini, dan keanggotaan
        // team_user tidak pernah diisi secara nyata — jadi semua user (kecuali VIP,
        // yang memang tidak membuat tiket) bisa pilih team manapun saat membuat tiket.
        return Team::query();
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

    public function applySlaDeadlines(Ticket $ticket, ?SlaPolicy $policy = null): void
    {
        $policy ??= $ticket->slaPolicy;

        if (! $policy) {
            return;
        }

        $startedAt = now();
        // SLA due dates are recalculated from the moment the workflow starts.
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

    public function storeAttachments(Ticket $ticket, array $files, ?string $userId = null, ?int $messageId = null): void
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
            'ticket_id'  => $ticket?->id,
            'user_id'    => $user?->id,
            'action'     => $action,
            'description'=> $description,
            'properties' => $properties,
        ]);
    }

    /**
     * Refleksikan status tiket ke kondisi aset PRISM (ast_main) yang dilaporkannya.
     * Selama tiket masih terbuka, aset ditandai 'Bad'. Begitu tidak ada lagi tiket
     * terbuka untuk aset yang sama, kondisinya dikembalikan ke sebelum dilaporkan.
     */
    public function syncLinkedAssetCondition(Ticket $ticket, ?string $previousAstId = null): void
    {
        if ($previousAstId && $previousAstId !== $ticket->ast_id) {
            $this->releaseAssetIfNoOpenTickets($previousAstId);
        }

        if (! $ticket->ast_id) {
            return;
        }

        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            $this->releaseAssetIfNoOpenTickets($ticket->ast_id);

            return;
        }

        $asset = AstMain::find($ticket->ast_id);

        if (! $asset || $asset->ast_last_ticket_id === $ticket->id) {
            return;
        }

        $ticket->update(['asset_cond_before' => $ticket->asset_cond_before ?? $asset->ast_cond]);
        $asset->update(['ast_cond' => 'Bad', 'ast_last_ticket_id' => $ticket->id]);
    }

    private function releaseAssetIfNoOpenTickets(string $astId): void
    {
        $stillOpen = Ticket::where('ast_id', $astId)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->exists();

        if ($stillOpen) {
            return;
        }

        $asset = AstMain::find($astId);

        if (! $asset || ! $asset->ast_last_ticket_id) {
            return;
        }

        $lastTicket = Ticket::find($asset->ast_last_ticket_id);

        $asset->update([
            'ast_cond' => $lastTicket?->asset_cond_before ?? 'Good',
            'ast_last_ticket_id' => null,
        ]);
    }

    public function notifyUsers(iterable $users, ?Ticket $ticket, string $type, string $title, string $message, array $data = []): void
    {
        // The same event writes both in-app notifications and email notifications.
        // Email delivery is filtered again by audience/type rules below.
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

            if ($this->shouldSendEmailNotification($user, $ticket, $type)) {
                $this->sendTicketEmail($user, $ticket, $title, $message, $data);
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

        $needles = collect($matches[1] ?? [])->map(fn (string $value) => Str::lower($value))->unique()->values();

        if ($needles->isEmpty()) {
            return collect();
        }

        // Narrow candidates in SQL with LIKE conditions, then apply exact
        // slug/email matching in PHP to avoid false positives from LIKE.
        $query = User::query();

        $query->where(function ($builder) use ($needles): void {
            foreach ($needles as $needle) {
                $builder->orWhere('user_email', 'like', $needle . '@%')
                        ->orWhere('user_name', 'like', '%' . $needle . '%');
            }
        });

        return $query->get()
            ->filter(function (User $user) use ($needles): bool {
                $emailLocal = Str::before(Str::lower($user->user_email), '@');
                $nameSlug   = Str::slug($user->user_name, '');

                return $needles->contains($emailLocal) || $needles->contains($nameSlug);
            })
            ->values();
    }

    public function defaultCustomFields(User $user): Collection
    {
        return CustomField::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function attachmentPathExists(string $path): bool
    {
        return Storage::exists($path);
    }

    public function statusNotificationMessage(string $status): string
    {
        return match ($status) {
            'in_progress' => 'Ticket sedang dikerjakan oleh tim support.',
            'pending' => 'Ticket sedang menunggu informasi atau tindak lanjut berikutnya.',
            'resolved' => 'Ticket sudah ditandai selesai dan menunggu konfirmasi.',
            'closed' => 'Ticket sudah ditutup.',
            default => 'Status ticket berubah menjadi '.Str::headline($status).'.',
        };
    }

    private function shouldSendEmailNotification(User $user, ?Ticket $ticket, string $type): bool
    {
        if (! $user->user_email || ! $ticket) {
            return false;
        }

        if (! in_array($type, config('helpdesk.mail.types', []), true)) {
            return false;
        }

        // Staff-facing types bypass the client_only audience filter.
        if (in_array($type, config('helpdesk.mail.staff_types', []), true)) {
            return true;
        }

        return match (config('helpdesk.mail.audience', 'client_only')) {
            'all'  => true,
            'none' => false,
            default => $user->isUser() && $user->id === $ticket->requester_id,
        };
    }

    private function sendTicketEmail(User $user, ?Ticket $ticket, string $title, string $message, array $data): void
    {
        // Keep the mail payload small and deterministic: primary recipient is the
        // requester/client, while global CC is injected from configuration.
        $send = function () use ($user, $ticket, $title, $message, $data): void {
            try {
                $mail = Mail::to($user->user_email);
                $ccRecipients = collect(config('helpdesk.mail.cc', []))
                    ->filter(fn ($email) => filled($email) && strcasecmp($email, $user->user_email) !== 0)
                    ->unique()
                    ->values()
                    ->all();

                if (! empty($ccRecipients)) {
                    $mail->cc($ccRecipients);
                }

                $mail->send(new TicketEventMail($ticket, $title, $message, $data));
            } catch (\Throwable $exception) {
                Log::warning('Failed to send ticket notification email.', [
                    'ticket_id' => $ticket?->id,
                    'user_id' => $user->id,
                    'email' => $user->user_email,
                    'error' => $exception->getMessage(),
                ]);
            }
        };

        match (config('helpdesk.mail.delivery', 'sync')) {
            'after_response' => app()->terminating($send),
            default => $send(),
        };
    }
}

