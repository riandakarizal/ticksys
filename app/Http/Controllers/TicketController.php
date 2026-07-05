<?php

namespace App\Http\Controllers;

use App\Http\Requests\Ticket\TicketStoreRequest;
use App\Http\Requests\Ticket\TicketUpdateRequest;
use App\Models\AppNotification;
use App\Models\Category;
use App\Models\SlaPolicy;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Support\Helpdesk;
use App\Support\TicketManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
            ->with(['requester', 'assignee', 'team', 'device', 'category', 'subcategory']);

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('subject', 'like', '%' . $search . '%')
                    ->orWhere('ticket_number', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
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
            'tickets'    => $query->latest()->paginate(12)->withQueryString(),
            'categories' => $this->categoryQuery($user, $projectIds)->whereNull('parent_id')->orderBy('name')->get(),
            'clients'    => $members->where('user_role', 'client')->sortBy('user_name')->values(),
            'projects'   => $projects,
            'statuses'   => Ticket::STATUSES,
            'priorities' => Ticket::PRIORITIES,
        ]);
    }

    public function create(Helpdesk $helpdesk): View
    {
        $user = Auth::user();
        $projects = $helpdesk->visibleProjects($user)
            ->with([
                'members' => fn ($query) => $query->orderBy('user_name'),
                'devices' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();

        $projectIds = $projects->pluck('id');
        $members = $this->projectMembers($projects);
        $customFields = $helpdesk->defaultCustomFields($user)
            ->reject(fn ($field) => in_array($field->key, ['affected_device', 'business_impact'], true))
            ->values();

        return view('tickets.create', [
            'categories'  => $this->categoryQuery($user, $projectIds)->whereNull('parent_id')->with('children')->orderBy('name')->get(),
            'projects'    => $projects,
            'devices'     => $projects->flatMap(fn (Team $project) => $project->devices)->unique('id')->values(),
            'agents'      => $members->whereIn('user_role', ['agent', 'supervisor', 'admin'])->sortBy('user_name')->values(),
            'clients'     => $members->where('user_role', 'client')->sortBy('user_name')->values(),
            'customFields' => $customFields,
            'priorities'  => Ticket::PRIORITIES,
            'slaPolicies' => SlaPolicy::query()->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(TicketStoreRequest $request, Helpdesk $helpdesk, TicketManager $ticketManager): RedirectResponse|JsonResponse
    {
        $ticket = $ticketManager->createTicket(auth()->user(), $request, $helpdesk);
        $project = $helpdesk->visibleProjects(auth()->user())
            ->with(['members:id,user_name,user_email,user_role'])
            ->find($ticket->team_id);

        $supervisors = $project?->members->whereIn('user_role', ['supervisor', 'admin']) ?? collect();

        $helpdesk->notifyUsers(
            $helpdesk->participants($ticket)->merge($supervisors),
            $ticket,
            'ticket_created',
            'Ticket ' . $ticket->ticket_number . ' created',
            'A new ticket has been created with subject: ' . $ticket->subject,
            ['ticket_id' => $ticket->id]
        );

        if ($ticket->assignee) {
            $helpdesk->notifyUsers(
                collect([$ticket->assignee]),
                $ticket,
                'ticket_assigned',
                'Ticket ' . $ticket->ticket_number . ' assigned to you',
                'A new ticket has been added to your queue.',
                ['ticket_id' => $ticket->id]
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message'       => 'Ticket created successfully.',
                'redirect'      => route('tickets.index'),
                'ticket_number' => $ticket->ticket_number,
            ]);
        }

        return redirect()->route('tickets.index')->with('success', 'Ticket created successfully.');
    }

    public function show(Ticket $ticket, Helpdesk $helpdesk): View
    {
        $this->authorize('view', $ticket);

        $user = Auth::user();
        AppNotification::query()
            ->where('user_id', $user->id)
            ->where('ticket_id', $ticket->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $projects = $helpdesk->visibleProjects($user)
            ->with([
                'members' => fn ($query) => $query->orderBy('user_name'),
                'devices' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();
        $projectIds = $projects->pluck('id');
        $members = $this->projectMembers($projects);

        $ticket->load([
            'requester',
            'creator',
            'assignee',
            'team',
            'device',
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
            'ticket'       => $ticket,
            'agents'       => $members->whereIn('user_role', ['agent', 'supervisor', 'admin'])->sortBy('user_name')->values(),
            'projects'     => $projects,
            'devices'      => $projects->flatMap(fn (Team $project) => $project->devices)->unique('id')->values(),
            'categories'   => $this->categoryQuery($user, $projectIds)->whereNull('parent_id')->with('children')->orderBy('name')->get(),
            'clients'      => $members->where('user_role', 'client')->sortBy('user_name')->values(),
            'statuses'     => Ticket::STATUSES,
            'priorities'   => Ticket::PRIORITIES,
            'mergeTargets' => $helpdesk->visibleTickets($user)
                ->whereKeyNot($ticket->id)
                ->whereNull('merged_into_ticket_id')
                ->whereIn('status', ['open', 'in_progress', 'pending'])
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function update(TicketUpdateRequest $request, Ticket $ticket, Helpdesk $helpdesk, TicketManager $ticketManager): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $ticketManager->updateTicket($ticket, $request, $helpdesk);

        return back()->with('success', 'Ticket updated successfully.');
    }

    public function download(Ticket $ticket, TicketAttachment $attachment, Helpdesk $helpdesk): BinaryFileResponse
    {
        $this->authorize('download', $ticket);
        abort_unless($attachment->ticket_id === $ticket->id, 404);
        abort_unless($helpdesk->attachmentPathExists($attachment->path), 404);

        return Storage::download($attachment->path, $attachment->original_name);
    }

    public function merge(Request $request, Ticket $ticket, Helpdesk $helpdesk, TicketManager $ticketManager): RedirectResponse
    {
        $this->authorize('merge', $ticket);

        if ($ticket->isClosed()) {
            return back()->withErrors(['ticket' => 'Closed tickets cannot be modified.']);
        }

        $target = $ticketManager->mergeTicket($ticket, $request, $helpdesk);

        return redirect()->route('tickets.show', $target)->with('success', 'Ticket merged successfully.');
    }

    public function split(Request $request, Ticket $ticket, Helpdesk $helpdesk, TicketManager $ticketManager): RedirectResponse
    {
        $this->authorize('split', $ticket);

        if ($ticket->isClosed()) {
            return back()->withErrors(['ticket' => 'Closed tickets cannot be modified.']);
        }

        $newTicket = $ticketManager->splitTicket($ticket, $request, $helpdesk);

        return redirect()->route('tickets.show', $newTicket)->with('success', 'New ticket created from split successfully.');
    }

    private function categoryQuery(User $user, Collection $projectIds): Builder
    {
        $query = Category::query();

        if ($projectIds->isNotEmpty()) {
            $query->whereHas('projects', fn (Builder $q) => $q->whereIn('teams.id', $projectIds));
        }

        return $query;
    }

    private function projectMembers(EloquentCollection $projects): Collection
    {
        return $projects
            ->flatMap(fn (Team $project) => $project->members)
            ->unique('id')
            ->values();
    }
}
