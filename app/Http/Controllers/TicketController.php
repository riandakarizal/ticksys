<?php

namespace App\Http\Controllers;

use App\Http\Requests\Ticket\TicketStoreRequest;
use App\Http\Requests\Ticket\TicketTransitionRequest;
use App\Http\Requests\Ticket\TicketUpdateRequest;
use App\Models\AppNotification;
use App\Models\AstMain;
use App\Models\Category;
use App\Models\PjctMain;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public function index(Request $request, Helpdesk $helpdesk): View
    {
        $user = Auth::user();
        $visible = $helpdesk->visibleTickets($user);

        $query = (clone $visible)
            ->with(['project:id,pjct_name,pjct_client,pjct_div', 'creator:id,user_name', 'category', 'subcategory'])
            ->withCount('assets');

        if ($request->boolean('needs_action')) {
            $helpdesk->whereNeedsAction($query, $user);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('subject', 'like', '%'.$search.'%')
                    ->orWhere('ticket_number', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        foreach (['status', 'priority', 'issue_type', 'pjct_id'] as $filter) {
            if ($value = $request->string($filter)->toString()) {
                $query->where('tickets.'.$filter, $value);
            }
        }

        if ($categoryId = $request->integer('category_id')) {
            $query->where('category_id', $categoryId);
        }

        // Client = pjct_main.pjct_client (sama seperti kolom "Client" di Project → Main).
        if ($client = $request->string('client')->toString()) {
            $query->whereHas('project', fn (Builder $q) => $q->where('pjct_client', $client));
        }

        if ($from = $request->input('date_from')) {
            $query->whereDate('tickets.created_at', '>=', $from);
        }

        if ($to = $request->input('date_to')) {
            $query->whereDate('tickets.created_at', '<=', $to);
        }

        $pjctProjects = $this->projectOptions($user);

        return view('tickets.index', [
            'tickets' => $query->latest('tickets.created_at')->paginate(25)->withQueryString(),
            'statusCounts' => (clone $visible)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'needsActionCount' => $helpdesk->whereNeedsAction(clone $visible, $user)->count(),
            'categories' => Category::query()->whereNull('parent_id')->orderBy('name')->get(),
            'clients' => $pjctProjects->pluck('pjct_client')->filter()->unique()->sort()->values(),
            'pjctProjects' => $pjctProjects,
            'statuses' => Ticket::STATUS_LABELS,
            'priorities' => Ticket::PRIORITIES,
            'issueTypes' => Ticket::ISSUE_TYPES,
        ]);
    }

    public function create(Helpdesk $helpdesk): View
    {
        $this->authorize('create', Ticket::class);

        return view('tickets.create', [
            'pjctProjects' => $this->projectOptions(Auth::user()),
            'categories' => Category::query()->whereNull('parent_id')->with('children')->orderBy('name')->get(),
            'customFields' => $helpdesk->defaultCustomFields(),
            'priorities' => Ticket::PRIORITIES,
            'issueTypes' => Ticket::ISSUE_TYPES,
        ]);
    }

    public function store(TicketStoreRequest $request, TicketManager $ticketManager): RedirectResponse|JsonResponse
    {
        $ticket = $ticketManager->createTicket($request->user(), $request);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Ticket '.$ticket->ticket_number.' berhasil dibuat.',
                'redirect' => route('tickets.show', $ticket),
                'ticket_number' => $ticket->ticket_number,
            ]);
        }

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket '.$ticket->ticket_number.' berhasil dibuat.');
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
            'creator.parent',
            'approver',
            'project',
            'assets',
            'category',
            'subcategory',
            'attachments.user',
            'messages.user',
            'messages.attachments',
            'customFieldValues.customField',
            'activityLogs.user',
        ]);

        $abilities = collect(['update', 'comment', ...array_keys(TicketManager::TRANSITIONS)])
            ->mapWithKeys(fn (string $ability) => [$ability => $user->can($ability, $ticket)]);

        return view('tickets.show', [
            'ticket' => $ticket,
            'abilities' => $abilities,
            'approver' => $helpdesk->approverFor($ticket),
            // Dokumen alur (bukan lampiran diskusi), dikelompokkan per jenis sesuai urutan alur.
            'documents' => $ticket->attachments
                ->whereNull('ticket_message_id')
                ->groupBy(fn (TicketAttachment $attachment) => $attachment->docTypeKey())
                ->sortBy(fn ($group, string $type) => array_search($type, array_keys(TicketAttachment::DOC_TYPES), true)),
            'categories' => $abilities['update'] ? Category::query()->whereNull('parent_id')->with('children')->orderBy('name')->get() : collect(),
            'priorities' => Ticket::PRIORITIES,
            'issueTypes' => Ticket::ISSUE_TYPES,
        ]);
    }

    public function update(TicketUpdateRequest $request, Ticket $ticket, TicketManager $ticketManager): RedirectResponse
    {
        $ticketManager->updateDetails($ticket, $request, $request->user());

        return back()->with('success', 'Detail ticket diperbarui.');
    }

    public function transition(TicketTransitionRequest $request, Ticket $ticket, string $action, TicketManager $ticketManager): RedirectResponse
    {
        $ticketManager->transition($ticket, $action, $request, $request->user());

        return back()->with('success', TicketManager::TRANSITIONS[$action]['label'].'.');
    }

    /** Device milik satu project, untuk pemilih device di form tiket (dibatasi divisi user). */
    public function projectAssets(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('create', Ticket::class), 403);

        $project = PjctMain::query()->findOrFail($request->string('pjct_id')->toString());
        abort_unless($user->coversDivision($project->pjct_div), 403);

        $assets = AstMain::query()
            ->where('ast_pjctid', $project->id)
            ->orderBy('ast_type')
            ->orderBy('id')
            ->get(['id', 'ast_type', 'ast_brand', 'ast_brandmodel', 'ast_serial', 'ast_userloc', 'ast_cond', 'ast_last_ticket_id']);

        return response()->json([
            'assets' => $assets->map(fn (AstMain $asset) => [
                'id' => $asset->id,
                'label' => trim($asset->ast_type.' — '.$asset->ast_brand.' '.$asset->ast_brandmodel),
                'serial' => $asset->ast_serial,
                'location' => $asset->ast_userloc,
                'cond' => $asset->ast_cond,
                'has_ticket' => $asset->ast_last_ticket_id !== null,
            ]),
        ]);
    }

    public function download(Ticket $ticket, TicketAttachment $attachment, Helpdesk $helpdesk): StreamedResponse
    {
        $this->authorize('download', $ticket);
        abort_unless((int) $attachment->ticket_id === (int) $ticket->id, 404);
        abort_unless($helpdesk->attachmentPathExists($attachment->path), 404);

        // PDF & gambar dibuka langsung di browser, sisanya diunduh.
        $inline = str_starts_with((string) $attachment->mime_type, 'image/') || $attachment->mime_type === 'application/pdf';

        return Storage::response($attachment->path, $attachment->original_name, [], $inline ? 'inline' : 'attachment');
    }

    private function projectOptions(User $user): EloquentCollection
    {
        $allowedDivs = $user->allowedDivCodes();

        return PjctMain::query()
            ->when($allowedDivs !== null, fn (Builder $q) => $q->whereIn('pjct_div', $allowedDivs))
            ->orderBy('pjct_name')
            ->get(['id', 'pjct_name', 'pjct_client', 'pjct_div', 'pjct_type']);
    }
}
