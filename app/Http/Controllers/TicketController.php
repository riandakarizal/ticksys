<?php

namespace App\Http\Controllers;

use App\Http\Requests\Ticket\TicketStoreRequest;
use App\Http\Requests\Ticket\TicketUpdateRequest;
use App\Models\AppNotification;
use App\Models\AstMain;
use App\Models\Category;
use App\Models\PjctMain;
use App\Models\SlaPolicy;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TicketController extends Controller
{
    public function index(Request $request, Helpdesk $helpdesk): View
    {
        $user = Auth::user();

        $query = $helpdesk->visibleTickets($user)
            ->with(['requester', 'assignee', 'team', 'asset', 'project', 'category', 'subcategory']);

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

        if ($pjctId = $request->string('pjct_id')->toString()) {
            $query->where('pjct_id', $pjctId);
        }

        if ($categoryId = $request->integer('category_id')) {
            $query->where('category_id', $categoryId);
        }

        // Client di sini adalah nama klien project PRISM (pjct_main.pjct_client,
        // sama seperti kolom "Client" di Project → Main).
        if ($client = $request->string('client')->toString()) {
            $query->whereHas('project', fn (Builder $q) => $q->where('pjct_client', $client));
        }

        if ($from = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $pjctProjects = $this->pjctProjectOptions($user);

        return view('tickets.index', [
            'tickets'       => $query->latest()->paginate(25)->withQueryString(),
            'categories'    => $this->categoryQuery()->whereNull('parent_id')->orderBy('name')->get(),
            // Nama klien dari Project → Main (pjct_main.pjct_client), bukan user aplikasi.
            'clients'       => $pjctProjects->pluck('pjct_client')->filter()->unique()->sort()->values(),
            'pjctProjects'  => $pjctProjects,
            'statuses'      => Ticket::STATUSES,
            'priorities'    => Ticket::PRIORITIES,
        ]);
    }

    public function create(Helpdesk $helpdesk): View
    {
        $user = Auth::user();
        $customFields = $helpdesk->defaultCustomFields($user);

        return view('tickets.create', [
            'categories'  => $this->categoryQuery()->whereNull('parent_id')->with('children')->orderBy('name')->get(),
            // Project PRISM — memilihnya menentukan client (pjct_client) secara otomatis.
            'pjctProjects' => $this->pjctProjectOptions($user),
            // Aset PRISM (ast_main) yang bisa dikaitkan ke tiket, lintas project —
            // ponytail: select penuh, ganti ke endpoint pencarian async kalau ast_main tumbuh besar.
            'assets'      => $this->assetOptions(),
            // Semua user dengan role staff — bukan cuma yang kebetulan terdaftar sebagai
            // member Team, karena keanggotaan Team tidak dipakai secara nyata.
            'agents'      => User::query()->whereIn('user_role', ['siteadmin', 'admin', 'superadmin'])->orderBy('user_name')->get(),
            'customFields' => $customFields,
            'priorities'  => Ticket::PRIORITIES,
            'slaPolicies' => SlaPolicy::query()->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(TicketStoreRequest $request, Helpdesk $helpdesk, TicketManager $ticketManager): RedirectResponse|JsonResponse
    {
        $ticket = $ticketManager->createTicket(auth()->user(), $request, $helpdesk);
        $supervisors = User::query()->whereIn('user_role', ['admin', 'superadmin'])->get();

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

        $ticket->load([
            'requester',
            'creator',
            'assignee',
            'team',
            'asset',
            'project',
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
            'agents'       => User::query()->whereIn('user_role', ['siteadmin', 'admin', 'superadmin'])->orderBy('user_name')->get(),
            'pjctProjects' => $this->pjctProjectOptions($user),
            'assets'       => $this->assetOptions(),
            'categories'   => $this->categoryQuery()->whereNull('parent_id')->with('children')->orderBy('name')->get(),
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

    private function categoryQuery(): Builder
    {
        // Kategori tiket bersifat global — pemetaan category_team tidak pernah dipakai
        // secara nyata (0 baris di database), jadi tidak ada gunanya membatasi per-project.
        return Category::query();
    }

    private function pjctProjectOptions(User $user): EloquentCollection
    {
        $allowedDivs = $user->allowedDivCodes();

        return PjctMain::query()
            ->when($allowedDivs !== null, fn (Builder $q) => $q->whereIn('pjct_div', $allowedDivs))
            ->orderBy('pjct_name')
            ->get(['id', 'pjct_name', 'pjct_client']);
    }

    private function assetOptions(): EloquentCollection
    {
        return AstMain::query()
            ->with('project:id,pjct_name')
            ->orderBy('ast_pjctid')
            ->orderBy('id')
            ->get(['id', 'ast_type', 'ast_brand', 'ast_brandmodel', 'ast_userloc', 'ast_pjctid']);
    }
}
