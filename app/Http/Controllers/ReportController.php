<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, \App\Support\Helpdesk $helpdesk): View
    {
        $user = Auth::user();
        abort_unless($user->canViewReports(), 403);

        $query = $this->filteredQuery($request, $helpdesk);
        $tickets = (clone $query)->with(['requester', 'category', 'assignee'])->latest()->paginate(20)->withQueryString();
        $collection = (clone $query)->with(['requester', 'category', 'assignee'])->get();

        $averageResolutionMinutes = round($collection
            ->filter(fn (Ticket $ticket) => $ticket->resolved_at)
            ->avg(fn (Ticket $ticket) => $ticket->created_at->diffInMinutes($ticket->resolved_at)) ?? 0, 1);

        $breached = $collection->filter(fn (Ticket $ticket) => $ticket->isResponseBreached() || $ticket->isResolutionBreached())->count();
        $breachRate = $collection->count() ? round(($breached / $collection->count()) * 100, 1) : 0;

        return view('reports.index', [
            'tickets' => $tickets,
            'ticketCount' => $collection->count(),
            'byCategory' => $collection->groupBy(fn ($ticket) => $ticket->category?->name ?? 'Uncategorized')->map->count(),
            'averageResolutionMinutes' => $averageResolutionMinutes,
            'breachRate' => $breachRate,
            'categories' => Category::query()->where('tenant_id', $user->tenant_id)->whereNull('parent_id')->orderBy('name')->get(),
            'clients' => $helpdesk->visibleProjects($user)->with('members:id,name,role')->get()->flatMap->members->where('role', 'client')->unique('id')->sortBy('name')->values(),
            'projects' => $helpdesk->visibleProjects($user)->orderBy('name')->get(),
        ]);
    }

    public function export(Request $request, \App\Support\Helpdesk $helpdesk): StreamedResponse
    {
        $user = Auth::user();
        abort_unless($user->canViewReports(), 403);

        $tickets = $this->filteredQuery($request, $helpdesk)->with(['requester', 'category', 'assignee', 'team'])->latest()->get();

        return response()->streamDownload(function () use ($tickets): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Ticket Number', 'Subject', 'Status', 'Priority', 'Client', 'Category', 'Assignee', 'Project', 'Created At']);

            foreach ($tickets as $ticket) {
                fputcsv($handle, [
                    $ticket->ticket_number,
                    $ticket->subject,
                    $ticket->status,
                    $ticket->priority,
                    $ticket->requester?->name,
                    $ticket->category?->name,
                    $ticket->assignee?->name,
                    $ticket->team?->name,
                    $ticket->created_at->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        }, 'ticket-report-'.now()->format('Ymd-His').'.csv');
    }

    private function filteredQuery(Request $request, \App\Support\Helpdesk $helpdesk)
    {
        $query = $helpdesk->visibleTickets(Auth::user());

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

        return $query;
    }
}
