<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\PjctMain;
use App\Models\Ticket;
use App\Support\Helpdesk;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, Helpdesk $helpdesk): View
    {
        $user = Auth::user();
        abort_unless($user->canViewReports(), 403);

        $query = $this->filteredQuery($request, $helpdesk);
        $tickets = (clone $query)->with(['project', 'creator', 'category'])->latest()->paginate(25)->withQueryString();
        $collection = (clone $query)->with(['category'])->get();

        $averageResolutionMinutes = round($collection
            ->filter(fn (Ticket $ticket) => $ticket->resolved_at)
            ->avg(fn (Ticket $ticket) => $ticket->created_at->diffInMinutes($ticket->resolved_at)) ?? 0, 1);

        $breached = $collection->filter(fn (Ticket $ticket) => $ticket->isResolutionBreached())->count();
        $breachRate = $collection->count() ? round(($breached / $collection->count()) * 100, 1) : 0;
        $allowedDivs = $user->allowedDivCodes();

        return view('reports.index', [
            'tickets' => $tickets,
            'ticketCount' => $collection->count(),
            'byCategory' => $collection->groupBy(fn (Ticket $ticket) => $ticket->issueTypeLabel())->map->count(),
            'averageResolutionMinutes' => $averageResolutionMinutes,
            'breachRate' => $breachRate,
            'categories' => Category::query()->whereNull('parent_id')->orderBy('name')->get(),
            'projects' => PjctMain::query()
                ->when($allowedDivs !== null, fn (Builder $q) => $q->whereIn('pjct_div', $allowedDivs))
                ->orderBy('pjct_name')
                ->get(['id', 'pjct_name']),
            'statuses' => Ticket::STATUS_LABELS,
            'issueTypes' => Ticket::ISSUE_TYPES,
        ]);
    }

    public function export(Request $request, Helpdesk $helpdesk): StreamedResponse
    {
        $user = Auth::user();
        abort_unless($user->canViewReports(), 403);

        $tickets = $this->filteredQuery($request, $helpdesk)->with(['project', 'creator', 'category', 'assets'])->latest()->get();

        return response()->streamDownload(function () use ($tickets): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Ticket Number', 'Subject', 'Jenis', 'Status', 'Priority', 'Project', 'Client', 'Device', 'Teknisi', 'Category', 'Created At', 'Solved At', 'Closed At']);

            foreach ($tickets as $ticket) {
                fputcsv($handle, [
                    $ticket->ticket_number,
                    $ticket->subject,
                    $ticket->issueTypeLabel(),
                    $ticket->statusLabel(),
                    $ticket->priority,
                    $ticket->project?->pjct_name,
                    $ticket->clientLabel(),
                    $ticket->assets->pluck('id')->implode(', '),
                    $ticket->creator?->user_name,
                    $ticket->category?->name,
                    $ticket->created_at->format('Y-m-d H:i'),
                    $ticket->resolved_at?->format('Y-m-d H:i'),
                    $ticket->closed_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        }, 'ticket-report-'.now()->format('Ymd-His').'.csv');
    }

    private function filteredQuery(Request $request, Helpdesk $helpdesk): Builder
    {
        $query = $helpdesk->visibleTickets(Auth::user());

        foreach (['status', 'priority', 'issue_type', 'pjct_id'] as $filter) {
            if ($value = $request->string($filter)->toString()) {
                $query->where($filter, $value);
            }
        }

        if ($categoryId = $request->integer('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($from = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }
}
