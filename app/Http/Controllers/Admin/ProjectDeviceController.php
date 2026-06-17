<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminController;
use App\Models\Team;
use App\Models\Ticket;
use App\Support\Helpdesk;
use Illuminate\View\View;

class ProjectDeviceController extends AdminController
{
    public function show(Team $team, Helpdesk $helpdesk): View
    {
        $this->ensureCompanyRecord($team);

        $team->load(['lead:id,name', 'members:id,name,role']);
        $projectDevices = $team->devices()
            ->withCount('tickets')
            ->withExists(['tickets as has_open_ticket' => fn ($query) => $query->where('status', '!=', 'closed')])
            ->orderBy('name')
            ->get();
        $recentTickets = Ticket::query()
            ->where('company_id', $team->company_id)
            ->where('team_id', $team->id)
            ->with(['device:id,name', 'requester:id,name'])
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.project-devices', array_merge(
            $this->viewData('Project Devices', 'Review device inventory and ticket usage per project.'),
            [
                'project' => $team,
                'projectDevices' => $projectDevices,
                'recentTickets' => $recentTickets,
            ]
        ));
    }
}
