<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\User;
use App\Support\Helpdesk;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:run-helpdesk-automation')]
#[Description('Run SLA escalation and auto-close automation for helpdesk tickets')]
class RunHelpdeskAutomation extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(Helpdesk $helpdesk): int
    {
        $escalated = 0;
        $closed = 0;

        Ticket::query()
            ->with(['tenant'])
            ->whereIn('status', ['open', 'in_progress', 'pending'])
            ->where(function ($query): void {
                $query->where(function ($inner): void {
                    $inner->whereNull('first_responded_at')->where('response_due_at', '<', now());
                })->orWhere('resolution_due_at', '<', now());
            })
            ->get()
            ->each(function (Ticket $ticket) use (&$escalated, $helpdesk): void {
                $metadata = $ticket->metadata ?? [];
                if (! empty($metadata['escalated_at'])) {
                    return;
                }

                $supervisors = User::query()
                    ->where('tenant_id', $ticket->tenant_id)
                    ->whereIn('role', ['supervisor', 'admin'])
                    ->get();

                $metadata['escalated_at'] = now()->toDateTimeString();
                $ticket->update(['metadata' => $metadata]);

                $helpdesk->recordActivity($ticket, null, 'sla_escalated', 'Ticket di-escalate otomatis');
                $helpdesk->notifyUsers($supervisors, $ticket, 'sla_escalated', 'SLA ticket '.$ticket->ticket_number.' terlewati', 'Ticket membutuhkan perhatian karena SLA terlewati.', ['ticket_id' => $ticket->id]);
                $escalated++;
            });

        Ticket::query()
            ->with('tenant')
            ->where('status', 'resolved')
            ->with(['tenant', 'requester', 'assignee', 'team'])
            ->whereNotNull('resolved_at')
            ->get()
            ->each(function (Ticket $ticket) use (&$closed, $helpdesk): void {
                if ($ticket->resolved_at->addDays($ticket->tenant->auto_close_days)->isFuture()) {
                    return;
                }

                $ticket->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                ]);

                $helpdesk->recordActivity($ticket, null, 'ticket_auto_closed', 'Ticket ditutup otomatis');
                $helpdesk->notifyUsers(
                    collect([$ticket->requester, $ticket->assignee])->filter(),
                    $ticket,
                    'ticket_status_changed',
                    'Status ticket '.$ticket->ticket_number.' berubah',
                    $helpdesk->statusNotificationMessage('closed'),
                    [
                        'ticket_id' => $ticket->id,
                        'status' => 'closed',
                        'from_status' => 'resolved',
                    ]
                );
                $closed++;
            });

        $this->info("Escalated: {$escalated}; Auto closed: {$closed}");

        return self::SUCCESS;
    }
}
