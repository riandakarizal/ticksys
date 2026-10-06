<?php

namespace App\Support;

use App\Http\Requests\Ticket\TicketStoreRequest;
use App\Http\Requests\Ticket\TicketUpdateRequest;
use App\Models\Category;
use App\Models\PjctMain;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketManager
{
    public function createTicket(User $user, TicketStoreRequest $request, Helpdesk $helpdesk): Ticket
    {
        $customFields = $helpdesk->defaultCustomFields($user)->values();
        $this->validateTicketRelationships($request, $customFields);

        $project = PjctMain::findOrFail($request->validated('pjct_id'));

        return DB::transaction(function () use ($request, $user, $customFields, $helpdesk, $project) {
            $slaId = $request->validated('sla_policy_id') ?? SlaPolicy::query()
                ->where('is_default', true)
                ->value('id');

            $ticket = Ticket::create([
                // Self-service: requester adalah akun sendiri. Dibuatkan staff atas nama
                // client: tidak ada akun asli, nama client diambil dari project PRISM.
                'requester_id'   => $user->isUser() ? $user->id : null,
                'requester_name' => $user->isUser() ? null : $project->pjct_client,
                'created_by'    => $user->id,
                'assigned_to'   => $request->validated('assigned_to'),
                'pjct_id'       => $project->id,
                'ast_id'        => $request->validated('ast_id'),
                'category_id'   => $request->validated('category_id'),
                'subcategory_id' => $request->validated('subcategory_id'),
                'sla_policy_id' => $slaId,
                'subject'       => $request->validated('subject'),
                'description'   => $request->validated('description'),
                'priority'      => $request->validated('priority'),
                'status'        => 'open',
                'tags'          => $helpdesk->parseTags($request->validated('tags') ?? ''),
            ]);

            $helpdesk->syncCustomFields($ticket, $customFields, $request->input('custom_fields', []));
            $ticket->loadMissing('slaPolicy');
            $helpdesk->applySlaDeadlines($ticket, $ticket->slaPolicy);
            $ticket->save();
            $helpdesk->storeAttachments($ticket, $request->file('attachments', []), $user->id);
            $helpdesk->recordActivity($ticket, $user, 'ticket_created', 'Ticket dibuat', [
                'status'      => 'open',
                'priority'    => $ticket->priority,
                'project_id'  => $ticket->pjct_id,
                'assigned_to' => $ticket->assigned_to,
            ]);
            $this->recordStatusHistory($helpdesk, $ticket, $user, null, 'open');
            $helpdesk->syncLinkedAssetCondition($ticket);

            return $ticket->load(['requester', 'assignee', 'project', 'asset']);
        });
    }

    public function updateTicket(Ticket $ticket, TicketUpdateRequest $request, Helpdesk $helpdesk): Ticket
    {
        if ($ticket->isClosed()) {
            throw ValidationException::withMessages(['ticket' => 'Ticket yang sudah closed tidak bisa diperbarui lagi.']);
        }

        $user = auth()->user();
        $this->validateTicketRelationships($request, collect());

        $before = $ticket->only(['status', 'priority', 'assigned_to', 'pjct_id', 'ast_id', 'category_id', 'subcategory_id']);

        $ticket->fill($request->validated());

        // Tiket dibuatkan staff atas nama client (bukan self-service): kalau project-nya
        // diganti, ikut sinkronkan nama client yang ditampilkan.
        if (! $ticket->requester_id && $before['pjct_id'] !== $ticket->pjct_id) {
            $ticket->requester_name = PjctMain::find($ticket->pjct_id)?->pjct_client;
        }

        if (($before['status'] ?? null) !== $ticket->status && $ticket->status === 'in_progress') {
            // Only mark the first response time; do NOT recalculate SLA deadlines —
            // the clock started when the ticket was created.
            $ticket->first_responded_at ??= now();
        }

        if ($ticket->status === 'resolved' && ! $ticket->resolved_at) {
            $ticket->resolved_at         = now();
            $ticket->auto_close_warned_at = null;
        }

        if ($ticket->status === 'closed' && ! $ticket->closed_at) {
            $ticket->closed_at = now();
        }

        if (! in_array($ticket->status, ['resolved', 'closed'], true)) {
            $ticket->resolved_at = null;
        }

        if ($ticket->status !== 'closed') {
            $ticket->closed_at = null;
        }

        $ticket->save();
        $helpdesk->recordActivity($ticket, $user, 'ticket_updated', 'Ticket diperbarui', [
            'before' => $before,
            'after'  => $ticket->only(array_keys($before)),
        ]);

        if (($before['status'] ?? null) !== $ticket->status) {
            $this->recordStatusHistory($helpdesk, $ticket, $user, $before['status'] ?? null, $ticket->status);
        }

        $this->notifyTicketWorkflowChanges($helpdesk, $ticket, $before, $user);

        if (($before['ast_id'] ?? null) !== $ticket->ast_id) {
            $helpdesk->syncLinkedAssetCondition($ticket, $before['ast_id'] ?? null);
        }
        $helpdesk->syncLinkedAssetCondition($ticket);

        return $ticket;
    }

    public function mergeTicket(Ticket $ticket, Request $request, Helpdesk $helpdesk): Ticket
    {
        $data = $request->validate([
            'target_ticket_id' => ['required', 'integer'],
        ]);

        $target = $helpdesk->visibleTickets(Auth::user())->findOrFail($data['target_ticket_id']);
        abort_if($target->id === $ticket->id, 422, 'Tidak dapat merge ticket ke dirinya sendiri.');
        abort_if($target->merged_into_ticket_id !== null, 422, 'Target ticket sudah di-merge ke ticket lain.');

        return DB::transaction(function () use ($ticket, $target, $helpdesk): Ticket {
            $ticket->update([
                'merged_into_ticket_id' => $target->id,
                'status'                => 'closed',
                'closed_at'             => now(),
            ]);

            $helpdesk->recordActivity($ticket, Auth::user(), 'ticket_merged', 'Ticket digabungkan', [
                'target_ticket_id' => $target->id,
            ]);
            $this->recordStatusHistory($helpdesk, $ticket, Auth::user(), 'open', 'closed');

            return $target;
        });
    }

    public function splitTicket(Ticket $ticket, Request $request, Helpdesk $helpdesk): Ticket
    {
        $data = $request->validate([
            'subject'     => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        return DB::transaction(function () use ($ticket, $data, $helpdesk): Ticket {
            $newTicket = Ticket::create([
                'requester_id'        => $ticket->requester_id,
                'requester_name'      => $ticket->requester_name,
                'created_by'          => Auth::id(),
                'assigned_to'         => $ticket->assigned_to,
                'pjct_id'             => $ticket->pjct_id,
                'ast_id'              => $ticket->ast_id,
                'category_id'         => $ticket->category_id,
                'subcategory_id'      => $ticket->subcategory_id,
                'sla_policy_id'       => $ticket->sla_policy_id,
                'split_from_ticket_id' => $ticket->id,
                'subject'             => $data['subject'],
                'description'         => $data['description'],
                'status'              => 'open',
                'priority'            => $ticket->priority,
                'tags'                => $ticket->tags,
            ]);

            $newTicket->loadMissing('slaPolicy');
            $helpdesk->applySlaDeadlines($newTicket, $newTicket->slaPolicy);
            $newTicket->save();

            $helpdesk->recordActivity($newTicket, Auth::user(), 'ticket_split', 'Ticket hasil split dibuat', [
                'parent_ticket_id' => $ticket->id,
            ]);
            $this->recordStatusHistory($helpdesk, $newTicket, Auth::user(), null, 'open');
            $helpdesk->syncLinkedAssetCondition($newTicket);

            return $newTicket;
        });
    }

    private function validateTicketRelationships($request, $customFields): void
    {
        $categoryId    = $request->input('category_id');
        $subcategoryId = $request->input('subcategory_id');

        if ($subcategoryId && ! $categoryId) {
            throw ValidationException::withMessages(['subcategory_id' => 'Pilih category utama sebelum memilih subcategory.']);
        }

        if ($subcategoryId) {
            $subcategory = Category::query()->find($subcategoryId);

            if (! $subcategory || (int) $subcategory->parent_id !== (int) $categoryId) {
                throw ValidationException::withMessages(['subcategory_id' => 'Subcategory tidak cocok dengan category yang dipilih.']);
            }
        }

        foreach ($customFields as $field) {
            $value = $request->input('custom_fields.' . $field->key);
            if ($field->is_required && blank($value)) {
                throw ValidationException::withMessages(['custom_fields.' . $field->key => $field->name . ' wajib diisi.']);
            }
        }
    }

    private function recordStatusHistory(Helpdesk $helpdesk, Ticket $ticket, User $actor, ?string $fromStatus, string $toStatus): void
    {
        $description = $fromStatus
            ? 'Status ticket berubah dari ' . Str::headline($fromStatus) . ' ke ' . Str::headline($toStatus)
            : 'Status awal ticket: ' . Str::headline($toStatus);

        $helpdesk->recordActivity($ticket, $actor, 'ticket_status_changed', $description, [
            'from' => $fromStatus,
            'to'   => $toStatus,
        ]);
    }

    private function notifyTicketWorkflowChanges(Helpdesk $helpdesk, Ticket $ticket, array $before, User $actor): void
    {
        $participants = $helpdesk->participants($ticket)
            ->reject(fn (User $participant) => $participant->id === $actor->id);

        $helpdesk->notifyUsers(
            $participants,
            $ticket,
            'ticket_updated',
            'Ticket ' . $ticket->ticket_number . ' diperbarui',
            'Status sekarang: ' . Str::headline($ticket->status) . '. Prioritas: ' . Str::headline($ticket->priority) . '.',
            ['ticket_id' => $ticket->id]
        );

        if (($before['assigned_to'] ?? null) !== $ticket->assigned_to && $ticket->assignee) {
            $helpdesk->notifyUsers(
                collect([$ticket->assignee]),
                $ticket,
                'ticket_assigned',
                'Ticket ' . $ticket->ticket_number . ' di-assign ke Anda',
                'Ticket ' . $ticket->ticket_number . ' sekarang menjadi tanggung jawab Anda.',
                ['ticket_id' => $ticket->id]
            );
        }

        if (($before['status'] ?? null) !== $ticket->status) {
            $message = $helpdesk->statusNotificationMessage($ticket->status);

            $helpdesk->notifyUsers(
                collect([$ticket->requester, $ticket->assignee])->filter(),
                $ticket,
                'ticket_status_changed',
                'Status ticket ' . $ticket->ticket_number . ' berubah',
                $message,
                [
                    'ticket_id'   => $ticket->id,
                    'status'      => $ticket->status,
                    'from_status' => $before['status'] ?? null,
                ]
            );
        }
    }
}
