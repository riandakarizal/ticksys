<?php

namespace App\Http\Controllers;

use App\Models\PjctMain;
use App\Models\Ticket;
use App\Support\Helpdesk;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Helpdesk $helpdesk): View
    {
        $user    = Auth::user();
        $visible = $helpdesk->visibleTickets($user);

        // ── Project analytics ────────────────────────────────────────────
        $allProjects = PjctMain::all();

        $projectKpi = [
            'total'  => $allProjects->count(),
            'og'     => $allProjects->where('pjct_status', 'OG')->count(),
            'hvr'    => $allProjects->where('pjct_status', 'HVR')->count(),
            'dly'    => $allProjects->where('pjct_status', 'DLY')->count(),
            'end'    => $allProjects->where('pjct_status', 'END')->count(),
            'nilai'  => $allProjects->sum('pjct_value'),
        ];

        $byType = [
            'RENT'   => $allProjects->where('pjct_type', 'RENT')->count(),
            'SUPPLY' => $allProjects->where('pjct_type', 'SUPPLY')->count(),
            'JASA'   => $allProjects->where('pjct_type', 'JASA')->count(),
        ];

        $byArea = $allProjects
            ->whereNotNull('pjct_area')
            ->groupBy('pjct_area')
            ->map->count()
            ->sortDesc()
            ->take(8);

        $byYear = $allProjects
            ->filter(fn ($p) => $p->pjct_codate !== null)
            ->groupBy(fn ($p) => $p->pjct_codate->year)
            ->map->count()
            ->sortKeys();

        $recentProjects = PjctMain::latest('pjct_codate')->limit(8)->get();

        // ── Ticket quick-stats ───────────────────────────────────────────
        $ticketCounts = [
            'open'        => (clone $visible)->where('status', 'open')->count(),
            'in_progress' => (clone $visible)->where('status', 'in_progress')->count(),
            'pending'     => (clone $visible)->where('status', 'pending')->count(),
            'resolved'    => (clone $visible)->where('status', 'resolved')->count(),
        ];

        return view('dashboard', [
            'user'           => $user,
            'projectKpi'     => $projectKpi,
            'byType'         => $byType,
            'byArea'         => $byArea,
            'byYear'         => $byYear,
            'recentProjects' => $recentProjects,
            'ticketCounts'   => $ticketCounts,
            'notifications'  => $user->notificationsFeed()->latest()->limit(5)->get(),
        ]);
    }
}
