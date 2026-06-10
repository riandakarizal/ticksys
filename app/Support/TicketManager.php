<?php

namespace App\Support;

use App\Http\Requests\Ticket\TicketStoreRequest;
use App\Http\Requests\Ticket\TicketUpdateRequest;
use App\Models\Category;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketManager
{
    public function createTicket(User $user, TicketStoreRequest $request, Helpdesk $helpdesk): Ticket
    {
        $projects = $helpdesk->visibleProjects($user)
            ->with(['members:id,name,email,role', 'devices:id,tenant_id,team_id,name,is_active'])
            ->get();

        $customFields = $helpdesk->defaultCustomFields($user)
            ->reject(fn ($field) => in_array($field->key, ['affected_device', 'business_impact'], true))
            ->values();

        $affectedDeviceField = $helpdesk->defaultCustomFields($user)->firstWhere('key', 'affected_device');
        $this->validateTicketRelationships($request, $user, $customFields, $projects);

        $project = $projects->firstWhere('id', (int) $request->validated('team_id'));
        $assignedUserId = $helpdesk->autoAssignUserForProject($project, $request->validated('assigned_to'));

        return DB::transaction(function () use ($request, $user, $customFields, $affectedDeviceField, $helpdesk, $project, $assignedUserId) {
            $slaId = $request->validated('sla_policy_id') ?? SlaPolicy::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('is_default', true)
                ->value('id');

            $ticket = Ticket::create([
                'tenant_id'     => $user->tenant_id,
                'requester_id'  => $user->isClient() ? $user->id : $request->validated('requester_id'),
                'created_by'    => $user->id,
                'assigned_to'   => $assignedUserId,
                'team_id'       => $project->id,
                'device_id'     => $request->validated('device_id'),
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
            $this->syncAffectedDeviceField($helpdesk, $ticket, $affectedDeviceField);
            $ticket->loadMissing('slaPolicy');
            $helpdesk->applySlaDeadlines($ticket, $ticket->slaPolicy);
            $ticket->save();
            $helpdesk->storeAttachments($ticket, $request->file('attachments', []), $user->id);
            $helpdesk->recordActivity($ticket, $user, 'ticket_created', 'Ticket dibuat', [
                'status'      => 'open',
                'priority'    => $ticket->priority,
                'project_id'  => $ticket->team_id,
                'assigned_to' => $ticket->assigned_to,
            ]);
            $this->recordStatusHistory($helpdesk, $ticket, $user, null, 'open');

            return $ticket->load(['requester', 'assignee', 'team', 'device']);
        });
    }

    public function updateTicket(Ticket $ticket, TicketUpdateRequest $request, Helpdesk $helpdesk): Ticket
    {
        if ($ticket->isClosed()) {
            throw ValidationException::withMessages(['ticket' => 'Ticket yang sudah closed tidak bisa diperbarui lagi.']);
        }

        $user = auth()->user();
        $projects = $helpdesk->visibleProjects($user)
            ->with(['members:id,name,email,role', 'devices:id,tenant_id,team_id,name,is_active'])
            ->get();

        $this->validateTicketRelationships($request, $user, collect(), $projects);

        $before = $ticket->only(['status', 'priority', 'assigned_to', 'team_id', 'device_id', 'category_id', 'subcategory_id', 'requester_id']);

        $ticket->fill($request->validated());

        if (($before['status'] ?? null) !== $ticket->status && $ticket->status === 'in_progress') {
            // Only mark the first response time; do NOT recalculate SLA deadlines —
            // the clock started when the ticket was created.
            $ticket->first_responded_at ??= now();
        }

        if ($ticket->status === 'resolved' && ! $ticket->resolved_at) {
            $ticket->resolved_at = now();
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
                'tenant_id'           => $ticket->tenant_id,
                'requester_id'        => $ticket->requester_id,
                'created_by'          => Auth::id(),
                'assigned_to'         => $ticket->assigned_to,
                'team_id'             => $ticket->team_id,
                'device_id'           => $ticket->device_id,
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

            return $newTicket;
        });
    }

    private function validateTicketRelationships($request, User $user, $customFields, EloquentCollection $projects): void
    {
        $projectId     = (int) $request->input('team_id');
        $categoryId    = $request->input('category_id');
        $subcategoryId = $request->input('subcategory_id');
        $requesterId   = $user->isClient() ? $user->id : (int) $request->input('requester_id');
        $assignedTo    = $request->input('assigned_to') ? (int) $request->input('assigned_to') : null;
        $deviceId      = $request->input('device_id') ? (int) $request->input('device_id') : null;

        $project = $projects->firstWhere('id', $projectId);
        if (! $project) {
            throw ValidationException::withMessages(['team_id' => 'Project yang dipilih tidak tersedia untuk user ini.']);
        }

        if (! $project->members->contains('id', $requesterId)) {
            throw ValidationException::withMessages(['requester_id' => 'Requester harus menjadi member dari project yang dipilih.']);
        }

        if ($assignedTo && ! $project->members->contains('id', $assignedTo)) {
            throw ValidationException::withMessages(['assigned_to' => 'Assignee harus menjadi member dari project yang dipilih.']);
        }

        if ($deviceId && ! $project->devices->contains('id', $deviceId)) {
            throw ValidationException::withMessages(['device_id' => 'Affected device harus berasal dari project yang dipilih.']);
        }

        if ($subcategoryId && ! $categoryId) {
            throw ValidationException::withMessages(['subcategory_id' => 'Pilih category utama sebelum memilih subcategory.']);
        }

        if ($categoryId) {
            $category = Category::query()
                ->where('tenant_id', $user->tenant_id)
                ->with('projects:id')
                ->find($categoryId);

            if (! $category) {
                throw ValidationException::withMessages(['category_id' => 'Category tidak valid untuk tenant ini.']);
            }

            if ($projectId && ! $category->projects->contains('id', $projectId)) {
                throw ValidationException::withMessages(['category_id' => 'Category tidak tersedia untuk project yang dipilih.']);
            }
        }

        if ($subcategoryId) {
            $subcategory = Category::query()->where('tenant_id', $user->tenant_id)->find($subcategoryId);

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

    private function syncAffectedDeviceField(Helpdesk $helpdesk, Ticket $ticket, $affectedDeviceField): void
    {
        if (! $affectedDeviceField) {
            return;
        }

        $helpdesk->syncCustomFields($ticket, collect([$affectedDeviceField]), [
            $affectedDeviceField->key => $ticket->device?->name,
        ]);
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

        if ((int) ($before['assigned_to'] ?? 0) !== (int) ($ticket->assigned_to ?? 0) && $ticket->assignee) {
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
