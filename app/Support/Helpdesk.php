<?php

namespace App\Support;

use App\Mail\TicketEventMail;
use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\AstMain;
use App\Models\CustomField;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketCustomFieldValue;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Helpdesk
{
    /**
     * Tiket yang boleh dilihat user:
     * - superadmin & vip: semua;
     * - teknisi: tiket di project penugasannya (pjct_emp, User::assignedProjectIds());
     * - lainnya: tiket project di divisinya (allowedDivCodes(), null = semua divisi);
     * - ditambah tiket yang ia buat sendiri, dan — untuk atasan — tiket buatan bawahan
     *   langsungnya (perlu di-approve).
     */
    public function visibleTickets(User $user): Builder
    {
        if ($user->isSuperAdmin() || $user->isVip()) {
            return Ticket::query();
        }

        if ($user->isFin()) {
            return Ticket::query()->whereRaw('1 = 0');
        }

        $assigned = $user->assignedProjectIds();
        $codes = $assigned === null ? $user->allowedDivCodes() : [];

        if ($assigned === null && $codes === null) {
            return Ticket::query();
        }

        return Ticket::query()->where(function (Builder $query) use ($user, $assigned, $codes): void {
            $assigned !== null
                ? $query->whereIn('tickets.pjct_id', $assigned)
                : $query->whereHas('project', fn (Builder $project) => $project->whereIn('pjct_div', $codes));

            $query->orWhere('tickets.created_by', $user->id)
                ->orWhereIn('tickets.created_by', $this->directReportIds($user));
        });
    }

    /**
     * Tiket yang menunggu tindakan user ini: teknisi → open buatannya; atasan → solved dari
     * bawahan langsung; tim divisi (admin/superadmin) → escalated di divisinya; superadmin juga
     * menjadi approver cadangan untuk tiket yang pembuatnya tidak punya atasan.
     */
    public function whereNeedsAction(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $scope) use ($user): void {
            $scope->where(fn (Builder $q) => $q->where('tickets.status', 'open')->where('tickets.created_by', $user->id))
                ->orWhere(fn (Builder $q) => $q->where('tickets.status', 'solved')->whereIn('tickets.created_by', $this->directReportIds($user)));

            if ($user->isAdmin() || $user->isSuperAdmin()) {
                $codes = $user->allowedDivCodes();
                $scope->orWhere(fn (Builder $q) => $q->where('tickets.status', 'escalated')
                    ->when($codes !== null, fn (Builder $w) => $w->whereHas('project', fn (Builder $p) => $p->whereIn('pjct_div', $codes))));
            }

            if ($user->isSuperAdmin()) {
                $scope->orWhere(fn (Builder $q) => $q->where('tickets.status', 'solved')
                    ->whereDoesntHave('creator.parent'));
            }
        });
    }

    /** Atasan langsung teknisi pembuat tiket — approver solved → closed. */
    public function approverFor(Ticket $ticket): ?User
    {
        return $ticket->creator?->parent;
    }

    /** Admin/superadmin yang divisinya mencakup project tiket — tim yang menerima eskalasi. */
    public function divisionTeam(Ticket $ticket): Collection
    {
        $divCode = $ticket->project?->pjct_div;

        return User::query()
            ->whereIn('user_role', ['admin', 'superadmin'])
            ->where('user_status', 'active')
            ->get()
            ->filter(fn (User $user) => $user->coversDivision($divCode))
            ->values();
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

    /** @return list<string> nama file yang tersimpan */
    public function storeAttachments(Ticket $ticket, array $files, ?string $userId, string $docType, ?int $messageId = null): array
    {
        $stored = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $ticket->attachments()->create([
                'ticket_message_id' => $messageId,
                'doc_type' => $docType,
                'user_id' => $userId,
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store('ticket-attachments'),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
            ]);

            $stored[] = $file->getClientOriginalName();
        }

        return $stored;
    }

    public function recordActivity(?Ticket $ticket, ?User $user, string $action, string $description, array $properties = []): void
    {
        ActivityLog::create([
            'ticket_id' => $ticket?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'description' => $description,
            'properties' => $properties,
        ]);
    }

    // ── Kondisi device (ast_main) ─────────────────────────────────────────────
    // Selama tiket belum closed, device yang terlibat ditandai 'Bad'. Kondisi aslinya dicatat di
    // ticket_assets.cond_before dan dipulihkan begitu tidak ada lagi tiket aktif untuk device itu.

    /**
     * Samakan device tiket dengan daftar $astIds — device baru ditandai Bad, device yang
     * dilepas dipulihkan. @return array{attached: list<string>, detached: list<string>}
     */
    public function syncTicketAssets(Ticket $ticket, array $astIds): array
    {
        $astIds = array_values(array_unique(array_filter(array_map('strval', $astIds))));
        $current = $ticket->assets()->get()->keyBy('id');

        $detached = $current->keys()->diff($astIds)->values()->all();
        $attached = array_values(array_diff($astIds, $current->keys()->all()));

        foreach ($detached as $astId) {
            $condBefore = $current[$astId]->pivot->cond_before;
            $ticket->assets()->detach($astId);
            $this->releaseAsset($astId, $condBefore, $ticket->id);
        }

        foreach ($attached as $astId) {
            $asset = AstMain::find($astId);

            if (! $asset) {
                continue;
            }

            $ticket->assets()->attach($astId, ['cond_before' => $this->conditionBeforeTickets($asset, $ticket->id)]);

            if (! $ticket->isClosed()) {
                $asset->update(['ast_cond' => 'Bad', 'ast_last_ticket_id' => $ticket->id]);
            }
        }

        return ['attached' => $attached, 'detached' => $detached];
    }

    /** Dipanggil saat tiket closed: pulihkan semua device-nya (kecuali masih ditahan tiket lain). */
    public function releaseTicketAssets(Ticket $ticket): void
    {
        foreach ($ticket->assets()->get() as $asset) {
            $this->releaseAsset($asset->id, $asset->pivot->cond_before, $ticket->id);
        }
    }

    /**
     * Kondisi device sebelum dilaporkan. Kalau device sedang ditahan tiket lain yang belum
     * closed, ast_cond-nya sudah 'Bad' karena tiket itu — pakai kondisi asli yang dicatat tiket tsb.
     */
    private function conditionBeforeTickets(AstMain $asset, int $ticketId): ?string
    {
        $other = $this->otherActiveTicketAssets($asset->id, $ticketId)->orderBy('ticket_assets.id')->first(['ticket_assets.cond_before']);

        return $other ? $other->cond_before : $asset->ast_cond;
    }

    private function releaseAsset(string $astId, ?string $condBefore, int $ticketId): void
    {
        $asset = AstMain::find($astId);

        if (! $asset) {
            return;
        }

        $otherTicketId = $this->otherActiveTicketAssets($astId, $ticketId)->orderByDesc('tickets.id')->value('tickets.id');

        if ($otherTicketId) {
            $asset->update(['ast_last_ticket_id' => $otherTicketId]);

            return;
        }

        $asset->update([
            'ast_cond' => $condBefore ?? 'Good',
            'ast_last_ticket_id' => null,
        ]);
    }

    private function otherActiveTicketAssets(string $astId, int $ticketId): \Illuminate\Database\Query\Builder
    {
        return DB::table('ticket_assets')
            ->join('tickets', 'tickets.id', '=', 'ticket_assets.ticket_id')
            ->where('ticket_assets.ast_id', $astId)
            ->where('tickets.id', '!=', $ticketId)
            ->where('tickets.status', '!=', 'closed');
    }

    // ── Notifikasi ────────────────────────────────────────────────────────────

    public function notifyUsers(iterable $users, ?Ticket $ticket, string $type, string $title, string $message, array $data = [], ?User $actor = null): void
    {
        $recipients = collect($users)
            ->filter(fn ($user) => $user instanceof User)
            ->reject(fn (User $user) => $actor && $user->id === $actor->id)
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

    /** Pihak yang mengikuti tiket: teknisi pembuat, atasannya, dan tim divisi selama escalated. */
    public function participants(Ticket $ticket): Collection
    {
        $participants = collect([$ticket->creator, $this->approverFor($ticket)])->filter();

        if ($ticket->status === 'escalated') {
            $participants = $participants->merge($this->divisionTeam($ticket));
        }

        return $participants->unique('id')->values();
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
                $builder->orWhere('user_email', 'like', $needle.'@%')
                    ->orWhere('user_name', 'like', '%'.$needle.'%');
            }
        });

        return $query->get()
            ->filter(function (User $user) use ($needles): bool {
                $emailLocal = Str::before(Str::lower($user->user_email), '@');
                $nameSlug = Str::slug($user->user_name, '');

                return $needles->contains($emailLocal) || $needles->contains($nameSlug);
            })
            ->values();
    }

    public function defaultCustomFields(): Collection
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

    /** @return Collection<int, string> id user bawahan langsung */
    private function directReportIds(User $user): Collection
    {
        return User::query()->where('user_parid', $user->id)->pluck('id');
    }

    private function shouldSendEmailNotification(User $user, ?Ticket $ticket, string $type): bool
    {
        if (! $user->user_email || ! $ticket) {
            return false;
        }

        if (config('helpdesk.mail.audience') === 'none') {
            return false;
        }

        return in_array($type, config('helpdesk.mail.types', []), true);
    }

    private function sendTicketEmail(User $user, ?Ticket $ticket, string $title, string $message, array $data): void
    {
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
