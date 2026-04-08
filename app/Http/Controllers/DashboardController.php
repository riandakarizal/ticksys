<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Support\Helpdesk;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Helpdesk $helpdesk): View
    {
        $user = Auth::user();
        $visible = $helpdesk->visibleTickets($user);

        $tickets = (clone $visible)
            ->with(['requester', 'assignee', 'category'])
            ->latest()
            ->limit(8)
            ->get();

        $statusCounts = collect(Ticket::STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => 0])
            ->merge((clone $visible)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'));

        $dueTickets = (clone $visible)
            ->whereIn('status', ['open', 'in_progress', 'pending'])
            ->whereNotNull('resolution_due_at')
            ->where('resolution_due_at', '<=', now()->addHours(2))
            ->count();

        $ticketVolume = (clone $visible)->count();
        $openWithinSla = (clone $visible)
            ->where(function ($query): void {
                $query->whereNull('resolution_due_at')->orWhere('resolution_due_at', '>', now());
            })
            ->count();
        $slaCompliance = $ticketVolume === 0 ? 100 : round(($openWithinSla / $ticketVolume) * 100, 1);

        $agentPerformance = collect();
        if ($user->canViewReports()) {
            $performanceCounts = (clone $visible)
                ->whereNotNull('assigned_to')
                ->select(
                    'assigned_to',
                    DB::raw("SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count"),
                    DB::raw("SUM(CASE WHEN status IN ('open', 'in_progress', 'pending') THEN 1 ELSE 0 END) as open_count")
                )
                ->groupBy('assigned_to')
                ->get()
                ->keyBy('assigned_to');

            $agentPerformance = User::query()
                ->where('tenant_id', $user->tenant_id)
                ->whereIn('id', $performanceCounts->keys())
                ->get()
                ->map(function (User $agent) use ($performanceCounts) {
                    $counts = $performanceCounts->get($agent->id);
                    $agent->resolved_count = (int) ($counts->resolved_count ?? 0);
                    $agent->open_count = (int) ($counts->open_count ?? 0);

                    return $agent;
                })
                ->sortByDesc('resolved_count')
                ->values();
        }

        $monthlySeries = collect(range(5, 1))->map(function (int $offset) use ($visible) {
            $start = now()->startOfMonth()->subMonths($offset);
            $end = $start->copy()->endOfMonth();

            return [
                'label' => $start->format('M'),
                'value' => (clone $visible)->whereBetween('created_at', [$start, $end])->count(),
            ];
        })->push([
            'label' => now()->format('M'),
            'value' => (clone $visible)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
        ])->values();

        $categorySeries = (clone $visible)
            ->leftJoin('categories', 'tickets.category_id', '=', 'categories.id')
            ->selectRaw('COALESCE(categories.name, ?) as label, COUNT(*) as total', ['Uncategorized'])
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'value' => (int) $row->total]);

        return view('dashboard', [
            'tickets' => $tickets,
            'statusCounts' => $statusCounts,
            'isClient' => $user->role === 'client',
            'assignedCount' => (clone $visible)->where('assigned_to', $user->id)->count(),
            'openRequestCount' => (clone $visible)->whereIn('status', ['open', 'in_progress', 'pending'])->count(),
            'ticketVolume' => $ticketVolume,
            'dueTickets' => $dueTickets,
            'slaCompliance' => $slaCompliance,
            'agentPerformance' => $agentPerformance,
            'notifications' => $user->notificationsFeed()->latest()->limit(5)->get(),
            'statusChart' => $this->buildStatusChart($statusCounts),
            'monthlySeries' => $monthlySeries,
            'categorySeries' => $categorySeries,
        ]);
    }

    private function buildStatusChart($statusCounts)
    {
        $colors = [
            'open' => '#2563eb',
            'in_progress' => '#d97706',
            'pending' => '#f59e0b',
            'resolved' => '#16a34a',
            'closed' => '#64748b',
        ];

        $total = max($statusCounts->sum(), 1);
        $offset = 0;
        $segments = [];

        foreach (Ticket::STATUSES as $status) {
            $value = (int) ($statusCounts[$status] ?? 0);
            $percentage = ($value / $total) * 100;
            $segments[] = [
                'label' => Str::headline($status),
                'value' => $value,
                'color' => $colors[$status],
                'dash' => number_format(($percentage / 100) * 251.2, 2, '.', ''),
                'offset' => number_format(-$offset, 2, '.', ''),
            ];
            $offset += ($percentage / 100) * 251.2;
        }

        return collect($segments);
    }
}
