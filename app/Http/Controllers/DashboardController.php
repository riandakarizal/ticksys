<?php

namespace App\Http\Controllers;

use App\Models\AstMain;
use App\Models\PjctMain;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Helpdesk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Helpdesk $helpdesk): View
    {
        $user    = Auth::user();
        $visible = $helpdesk->visibleTickets($user);

        // Teknisi: dashboard aset & issue di project penugasannya, tanpa analitik project/nilai kontrak.
        if ($user->isTicketTechnician()) {
            return $this->technician($user, $helpdesk, $visible);
        }

        // ── Project analytics ────────────────────────────────────────────
        $allProjects = PjctMain::query()->visibleTo($user)->get();

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

        $recentProjects = PjctMain::query()->visibleTo($user)->latest('pjct_codate')->limit(8)->get();

        return view('dashboard', [
            'user'           => $user,
            'projectKpi'     => $projectKpi,
            'byType'         => $byType,
            'byArea'         => $byArea,
            'byYear'         => $byYear,
            'recentProjects' => $recentProjects,
            'ticketCounts'   => $this->ticketCounts($visible),
            'notifications'  => $user->notificationsFeed()->latest()->limit(5)->get(),
        ]);
    }

    /** Dashboard teknisi: kondisi aset & ticket (issue) di project penugasannya (pjct_emp). */
    private function technician(User $user, Helpdesk $helpdesk, Builder $visible): View
    {
        $projectIds = $user->visibleProjectIds() ?? [];

        // Daftar ID project dipakai lewat whereIn — ast_main & pjct_main beda collation di MySQL.
        $assets = fn (): Builder => AstMain::query()->whereIn('ast_pjctid', $projectIds);

        $byCond = $assets()->toBase()->selectRaw('ast_cond, count(*) as total')->groupBy('ast_cond')->pluck('total', 'ast_cond');
        $assetCond = collect(['Excellence', 'Good', 'Fair', 'Bad'])
            ->mapWithKeys(fn (string $cond) => [$cond => (int) ($byCond[$cond] ?? 0)])
            ->put('Lainnya', (int) $byCond->except(['Excellence', 'Good', 'Fair', 'Bad'])->sum());

        $byStat = $assets()->toBase()->selectRaw('ast_stat, count(*) as total')->groupBy('ast_stat')->pluck('total', 'ast_stat');

        return view('dashboard-technician', [
            'user'          => $user,
            'projects'      => PjctMain::query()->whereIn('id', $projectIds)->orderBy('pjct_name')->get(['id', 'pjct_name', 'pjct_client', 'pjct_area']),
            'assetTotal'    => $assetCond->sum(),
            'assetCond'     => $assetCond,
            'assetStat'     => [
                'Aktif'     => (int) ($byStat['Aktif'] ?? 0),
                'Sewa'      => (int) ($byStat['Aktif-Sewa'] ?? 0) + (int) ($byStat['Aktif-SewaBeli'] ?? 0),
                'Back Up'   => (int) ($byStat['Back Up'] ?? 0),
                'Non-Aktif' => (int) ($byStat['Non-Aktif'] ?? 0),
            ],
            'assetTypes'    => $assets()->toBase()->selectRaw('ast_type, count(*) as total')->groupBy('ast_type')
                ->orderByDesc('total')->limit(6)->pluck('total', 'ast_type'),
            'badAssets'     => $assets()->where('ast_cond', 'Bad')->orderByDesc('ast_last_ticket_id')->limit(8)
                ->get(['id', 'ast_type', 'ast_brand', 'ast_brandmodel', 'ast_serial', 'ast_userloc', 'ast_last_ticket_id']),
            'ticketCounts'  => $this->ticketCounts($visible),
            'ticketWeeks'   => $this->ticketWeeks($visible),
            'needsAction'   => $helpdesk->whereNeedsAction(clone $visible, $user)->count(),
            'activeTickets' => (clone $visible)->whereIn('tickets.status', ['open', 'escalated', 'solved'])
                ->with(['project:id,pjct_name', 'assets'])->latest()->limit(8)->get(),
        ]);
    }

    /** @return array<string, int> jumlah ticket per status (Ticket::STATUSES) */
    private function ticketCounts(Builder $visible): array
    {
        $totals = (clone $visible)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(Ticket::STATUSES)->mapWithKeys(fn (string $status) => [$status => (int) ($totals[$status] ?? 0)])->all();
    }

    /**
     * Ticket yang dibuat per minggu (Senin–Minggu), $weeks minggu terakhir termasuk minggu ini,
     * dipecah menurut status sekarang: closed vs masih berjalan (open/escalated/solved).
     * Dikelompokkan di PHP supaya tidak bergantung fungsi tanggal MySQL (test jalan di SQLite).
     *
     * @return list<array{start: Carbon, berjalan: int, closed: int}>
     */
    private function ticketWeeks(Builder $visible, int $weeks = 8): array
    {
        $start = now()->startOfWeek()->subWeeks($weeks - 1);

        $buckets = [];
        for ($i = 0; $i < $weeks; $i++) {
            $week = $start->copy()->addWeeks($i);
            $buckets[$week->toDateString()] = ['start' => $week, 'berjalan' => 0, 'closed' => 0];
        }

        $rows = (clone $visible)->where('tickets.created_at', '>=', $start)->toBase()->get(['tickets.created_at', 'tickets.status']);
        foreach ($rows as $row) {
            $key = Carbon::parse($row->created_at)->startOfWeek()->toDateString();
            if (isset($buckets[$key])) {
                $buckets[$key][$row->status === 'closed' ? 'closed' : 'berjalan']++;
            }
        }

        return array_values($buckets);
    }
}
