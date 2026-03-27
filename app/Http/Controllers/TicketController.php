<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Category;
use App\Models\SlaPolicy;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Support\Helpdesk;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TicketController extends Controller
{
    public function index(Request $request, Helpdesk $helpdesk): View
    {
        $user = Auth::user();
        $projects = $helpdesk->visibleProjects($user)->orderBy('name')->get();
        $projectIds = $projects->pluck('id');

        $query = $helpdesk->visibleTickets($user)
            ->with(['requester', 'assignee', 'team', 'category', 'subcategory']);

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('subject', 'like', '%'.$search.'%')
                    ->orWhere('ticket_number', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        foreach (['status', 'priority'] as $filter) {
            if ($value = $request->string($filter)->toString()) {
                $query->where($filter, $value);
            }
        }

        if ($projectId = $request->integer('project_id')) {
            $query->where('team_id', $projectId);
        }

        if ($categoryId = $request->integer('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($clientId = $request->integer('requester_id')) {
            $query->where('requester_id', $clientId);
        }

        if ($from = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $members = $this->projectMembers($projects);

        return view('tickets.index', [
            'tickets' => $query->latest()->paginate(12)->withQueryString(),
            'categories' => $this->categoryQuery($user, $projectIds)->whereNull('parent_id')->orderBy('name')->get(),
            'clients' => $members->where('role', 'client')->sortBy('name')->values(),
            'projects' => $projects,
            'statuses' => Ticket::STATUSES,
            'priorities' => Ticket::PRIORITIES,
        ]);
    }

    public function create(Helpdesk $helpdesk): View
    {
        $user = Auth::user();
        $projects = $helpdesk->visibleProjects($user)
            ->with(['members' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        $projectIds = $projects->pluck('id');
        $members = $this->projectMembers($projects);

        return view('tickets.create', [
            'categories' => $this->categoryQuery($user, $projectIds)->whereNull('parent_id')->with(['children.projects:id,name', 'projects:id,name', 'autoAssignUser'])->orderBy('name')->get(),
            'projects' => $projects,
            'agents' => $members->whereIn('role', ['agent', 'supervisor', 'admin'])->sortBy('name')->values(),
            'clients' => $members->where('role', 'client')->sortBy('name')->values(),
            'customFields' => $helpdesk->defaultCustomFields($user),
            'priorities' => Ticket::PRIORITIES,
            'slaPolicies' => SlaPolicy::query()->where('tenant_id', $user->tenant_id)->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Helpdesk $helpdesk): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        $customFields = $helpdesk->defaultCustomFields($user);
        $validated = $request->validate($this->ticketRules($user));

        $projects = $helpdesk->visibleProjects($user)->with('members:id,name,email,role')->get();
        $this->validateTicketRelationships($request, $user, $customFields, $projects);

        $project = $projects->firstWhere('id', (int) $validated['team_id']);
        $category = ! empty($validated['category_id'])
            ? Category::query()->where('tenant_id', $user->tenant_id)->findOrFail($validated['category_id'])
            : null;

        $preferredAssigneeId = $category?->auto_assign_user_id ?? ($validated['assigned_to'] ?? null);
        $assignedUserId = $helpdesk->autoAssignUserForProject($project, $preferredAssigneeId);

        $ticket = DB::transaction(function () use ($validated, $request, $user, $customFields, $helpdesk, $project, $assignedUserId) {
            $slaId = $validated['sla_policy_id'] ?? SlaPolicy::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('is_default', true)
                ->value('id');

            $requesterId = $user->isClient() ? $user->id : $validated['requester_id'];

            $ticket = Ticket::create([
                'tenant_id' => $user->tenant_id,
                'requester_id' => $requesterId,
                'created_by' => $user->id,
                'assigned_to' => $assignedUserId,
                'team_id' => $project->id,
                'category_id' => $validated['category_id'] ?? null,
                'subcategory_id' => $validated['subcategory_id'] ?? null,
                'sla_policy_id' => $slaId,
                'subject' => $validated['subject'],
                'description' => $validated['description'],
                'priority' => $validated['priority'],
                'status' => 'open',
                'tags' => $helpdesk->parseTags($validated['tags'] ?? ''),
            ]);

            $helpdesk->syncCustomFields($ticket, $customFields, $request->input('custom_fields', []));
            $helpdesk->storeAttachments($ticket, $request->file('attachments', []), $user->id);
            $helpdesk->recordActivity($ticket, $user, 'ticket_created', 'Ticket dibuat', [
                'status' => 'open',
                'priority' => $ticket->priority,
                'project_id' => $ticket->team_id,
                'assigned_to' => $ticket->assigned_to,
            ]);
            $this->recordStatusHistory($helpdesk, $ticket, $user, null, 'open');

            return $ticket->load(['requester', 'assignee', 'team']);
        });

        $supervisors = $project->members->whereIn('role', ['supervisor', 'admin']);

        $helpdesk->notifyUsers(
            $helpdesk->participants($ticket)->merge($supervisors),
            $ticket,
            'ticket_created',
            'Ticket '.$ticket->ticket_number.' dibuat',
            'Ticket baru telah dibuat dengan subjek: '.$ticket->subject,
            ['ticket_id' => $ticket->id]
        );

        if ($ticket->assignee) {
            $helpdesk->notifyUsers(
                collect([$ticket->assignee]),
                $ticket,
                'ticket_assigned',
                'Ticket '.$ticket->ticket_number.' di-assign ke Anda',
                'Ticket baru telah masuk ke queue Anda.',
                ['ticket_id' => $ticket->id]
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Ticket berhasil dibuat.',
                'redirect' => route('tickets.show', $ticket),
                'ticket_number' => $ticket->ticket_number,
            ]);
        }

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket berhasil dibuat.');
    }

    public function show(Ticket $ticket, Helpdesk $helpdesk): View
    {
        $this->authorizeTicket($ticket, $helpdesk);

        $user = Auth::user();
        AppNotification::query()
            ->where('user_id', $user->id)
            ->where('ticket_id', $ticket->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $projects = $helpdesk->visibleProjects($user)
            ->with(['members' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();
        $projectIds = $projects->pluck('id');
        $members = $this->projectMembers($projects);

        $ticket->load([
            'requester',
            'creator',
            'assignee',
            'team',
            'category',
            'subcategory',
            'slaPolicy',
            'attachments',
            'messages.user',
            'messages.attachments',
            'customFieldValues.customField',
            'activityLogs.user',
        ]);

        return view('tickets.show', [
            'ticket' => $ticket,
            'agents' => $members->whereIn('role', ['agent', 'supervisor', 'admin'])->sortBy('name')->values(),
            'projects' => $projects,
            'categories' => $this->categoryQuery($user, $projectIds)->whereNull('parent_id')->with(['children.projects:id,name', 'projects:id,name', 'autoAssignUser'])->orderBy('name')->get(),
            'clients' => $members->where('role', 'client')->sortBy('name')->values(),
            'statuses' => Ticket::STATUSES,
            'priorities' => Ticket::PRIORITIES,
            'mergeTargets' => $helpdesk->visibleTickets($user)->whereKeyNot($ticket->id)->latest()->limit(20)->get(),
        ]);
    }

    public function update(Request $request, Ticket $ticket, Helpdesk $helpdesk): RedirectResponse
    {
        $this->authorizeTicket($ticket, $helpdesk);

        $user = Auth::user();
        abort_if($user->isClient(), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(Ticket::STATUSES)],
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $user->tenant_id)->whereIn('role', ['agent', 'supervisor', 'admin']))],
            'team_id' => ['required', Rule::exists('teams', 'id')->where(fn ($query) => $query->where('tenant_id', $user->tenant_id))],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('tenant_id', $user->tenant_id)->whereNull('parent_id'))],
            'subcategory_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('tenant_id', $user->tenant_id))],
            'requester_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $user->tenant_id)->where('role', 'client'))],
        ]);

        $projects = $helpdesk->visibleProjects($user)->with('members:id,name,email,role')->get();
        $this->validateTicketRelationships($request, $user, collect(), $projects);

        $before = $ticket->only(['status', 'priority', 'assigned_to', 'team_id', 'category_id', 'subcategory_id', 'requester_id']);

        $ticket->fill($validated);
        $ticket->loadMissing('slaPolicy');

        if (($before['status'] ?? null) !== $validated['status'] && $validated['status'] === 'in_progress') {
            $helpdesk->applySlaDeadlines($ticket, $ticket->slaPolicy);
            $ticket->first_responded_at ??= now();
        }

        if ($validated['status'] === 'resolved' && ! $ticket->resolved_at) {
            $ticket->resolved_at = now();
        }

        if ($validated['status'] === 'closed' && ! $ticket->closed_at) {
            $ticket->closed_at = now();
        }

        if ($validated['status'] !== 'resolved') {
            $ticket->resolved_at = null;
        }

        if ($validated['status'] !== 'closed') {
            $ticket->closed_at = null;
        }

        $ticket->save();

        $helpdesk->recordActivity($ticket, $user, 'ticket_updated', 'Ticket diperbarui', [
            'before' => $before,
            'after' => $ticket->only(array_keys($before)),
        ]);

        if (($before['status'] ?? null) !== $ticket->status) {
            $this->recordStatusHistory($helpdesk, $ticket, $user, $before['status'] ?? null, $ticket->status);
        }

        $this->notifyTicketWorkflowChanges($helpdesk, $ticket, $before, $user);

        return back()->with('success', 'Ticket berhasil diperbarui.');
    }

    public function download(Ticket $ticket, TicketAttachment $attachment, Helpdesk $helpdesk): BinaryFileResponse
    {
        $this->authorizeTicket($ticket, $helpdesk);
        abort_unless($attachment->ticket_id === $ticket->id, 404);
        abort_unless($helpdesk->attachmentPathExists($attachment->path), 404);

        return Storage::download($attachment->path, $attachment->original_name);
    }

    public function merge(Request $request, Ticket $ticket, Helpdesk $helpdesk): RedirectResponse
    {
        $this->authorizeTicket($ticket, $helpdesk);
        abort_if(Auth::user()->isClient(), 403);

        $data = $request->validate([
            'target_ticket_id' => ['required', 'integer'],
        ]);

        $target = $helpdesk->visibleTickets(Auth::user())->findOrFail($data['target_ticket_id']);
        abort_if($target->id === $ticket->id, 422);

        $ticket->update([
            'merged_into_ticket_id' => $target->id,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $helpdesk->recordActivity($ticket, Auth::user(), 'ticket_merged', 'Ticket digabungkan', [
            'target_ticket_id' => $target->id,
        ]);
        $this->recordStatusHistory($helpdesk, $ticket, Auth::user(), 'open', 'closed');

        return redirect()->route('tickets.show', $target)->with('success', 'Ticket berhasil di-merge.');
    }

    public function split(Request $request, Ticket $ticket, Helpdesk $helpdesk): RedirectResponse
    {
        $this->authorizeTicket($ticket, $helpdesk);
        abort_if(Auth::user()->isClient(), 403);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        $newTicket = Ticket::create([
            'tenant_id' => $ticket->tenant_id,
            'requester_id' => $ticket->requester_id,
            'created_by' => Auth::id(),
            'assigned_to' => $ticket->assigned_to,
            'team_id' => $ticket->team_id,
            'category_id' => $ticket->category_id,
            'subcategory_id' => $ticket->subcategory_id,
            'sla_policy_id' => $ticket->sla_policy_id,
            'split_from_ticket_id' => $ticket->id,
            'subject' => $data['subject'],
            'description' => $data['description'],
            'status' => 'open',
            'priority' => $ticket->priority,
            'tags' => $ticket->tags,
        ]);

        $helpdesk->recordActivity($newTicket, Auth::user(), 'ticket_split', 'Ticket hasil split dibuat', [
            'parent_ticket_id' => $ticket->id,
        ]);
        $this->recordStatusHistory($helpdesk, $newTicket, Auth::user(), null, 'open');

        return redirect()->route('tickets.show', $newTicket)->with('success', 'Ticket baru hasil split berhasil dibuat.');
    }

    private function authorizeTicket(Ticket $ticket, Helpdesk $helpdesk): void
    {
        $allowed = $helpdesk->visibleTickets(Auth::user())->whereKey($ticket->id)->exists();
        abort_unless($allowed, 403);
    }

    private function ticketRules(User $user): array
    {
        $tenantId = $user->tenant_id;

        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('parent_id'))],
            'subcategory_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'requester_id' => [$user->isClient() ? 'nullable' : 'required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('role', 'client'))],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereIn('role', ['agent', 'supervisor', 'admin']))],
            'team_id' => ['required', Rule::exists('teams', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'sla_policy_id' => ['nullable', Rule::exists('sla_policies', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'tags' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,txt,log,doc,docx,xls,xlsx,csv'],
        ];
    }

    private function validateTicketRelationships(Request $request, User $user, Collection $customFields, EloquentCollection $projects): void
    {
        $projectId = (int) $request->input('team_id');
        $categoryId = $request->input('category_id');
        $subcategoryId = $request->input('subcategory_id');
        $requesterId = $user->isClient() ? $user->id : (int) $request->input('requester_id');
        $assignedTo = $request->input('assigned_to') ? (int) $request->input('assigned_to') : null;

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

        if ($subcategoryId && ! $categoryId) {
            throw ValidationException::withMessages(['subcategory_id' => 'Pilih category utama sebelum memilih subcategory.']);
        }

        if ($categoryId) {
            $category = Category::query()
                ->where('tenant_id', $user->tenant_id)
                ->with('projects:id')
                ->find($categoryId);

            if ($category && $category->projects->isNotEmpty() && ! $category->projects->contains('id', $projectId)) {
                throw ValidationException::withMessages(['category_id' => 'Category ini tidak tersedia untuk project yang dipilih.']);
            }
        }

        if ($subcategoryId) {
            $subcategory = Category::query()->where('tenant_id', $user->tenant_id)->find($subcategoryId);

            if (! $subcategory || (int) $subcategory->parent_id !== (int) $categoryId) {
                throw ValidationException::withMessages(['subcategory_id' => 'Subcategory tidak cocok dengan category yang dipilih.']);
            }

            $subcategory->loadMissing('projects:id');
            if ($subcategory->projects->isNotEmpty() && ! $subcategory->projects->contains('id', $projectId)) {
                throw ValidationException::withMessages(['subcategory_id' => 'Subcategory ini tidak tersedia untuk project yang dipilih.']);
            }
        }

        foreach ($customFields as $field) {
            $value = $request->input('custom_fields.'.$field->key);
            if ($field->is_required && blank($value)) {
                throw ValidationException::withMessages(['custom_fields.'.$field->key => $field->name.' wajib diisi.']);
            }
        }
    }

    private function categoryQuery(User $user, Collection $projectIds)
    {
        return Category::query()
            ->where('tenant_id', $user->tenant_id)
            ->when(! $user->isAdmin(), function ($query) use ($projectIds): void {
                $query->where(function ($inner) use ($projectIds): void {
                    $inner
                        ->whereDoesntHave('projects')
                        ->orWhereHas('projects', fn ($builder) => $builder->whereIn('teams.id', $projectIds));
                });
            });
    }

    private function projectMembers(EloquentCollection $projects): Collection
    {
        return $projects
            ->flatMap(fn (Team $project) => $project->members)
            ->unique('id')
            ->values();
    }

    private function recordStatusHistory(Helpdesk $helpdesk, Ticket $ticket, User $actor, ?string $fromStatus, string $toStatus): void
    {
        $description = $fromStatus
            ? 'Status ticket berubah dari '.Str::headline($fromStatus).' ke '.Str::headline($toStatus)
            : 'Status awal ticket: '.Str::headline($toStatus);

        $helpdesk->recordActivity($ticket, $actor, 'ticket_status_changed', $description, [
            'from' => $fromStatus,
            'to' => $toStatus,
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
            'Ticket '.$ticket->ticket_number.' diperbarui',
            'Status sekarang: '.Str::headline($ticket->status).'. Prioritas: '.Str::headline($ticket->priority).'.',
            ['ticket_id' => $ticket->id]
        );

        if ((int) ($before['assigned_to'] ?? 0) !== (int) ($ticket->assigned_to ?? 0) && $ticket->assignee) {
            $helpdesk->notifyUsers(
                collect([$ticket->assignee]),
                $ticket,
                'ticket_assigned',
                'Ticket '.$ticket->ticket_number.' di-assign ke Anda',
                'Ticket '.$ticket->ticket_number.' sekarang menjadi tanggung jawab Anda.',
                ['ticket_id' => $ticket->id]
            );
        }

        if (($before['status'] ?? null) !== $ticket->status) {
            $message = match ($ticket->status) {
                'in_progress' => 'Ticket sedang dikerjakan oleh tim support.',
                'resolved' => 'Ticket sudah ditandai selesai dan menunggu konfirmasi.',
                'closed' => 'Ticket sudah ditutup.',
                default => 'Status ticket berubah menjadi '.Str::headline($ticket->status).'.',
            };

            $helpdesk->notifyUsers(
                collect([$ticket->requester, $ticket->assignee])->filter(),
                $ticket,
                'ticket_status_changed',
                'Status ticket '.$ticket->ticket_number.' berubah',
                $message,
                ['ticket_id' => $ticket->id, 'status' => $ticket->status]
            );
        }
    }
}
