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
        $warned    = 0;
        $closed    = 0;

        // === Warning: kirim notifikasi 1 hari sebelum auto-close ===
        Ticket::query()
            ->where('status', 'resolved')
            ->with(['company', 'requester'])
            ->whereNotNull('resolved_at')
            ->whereNull('auto_close_warned_at')
            ->get()
            ->each(function (Ticket $ticket) use (&$warned, $helpdesk): void {
                $days = $ticket->company->auto_close_days;
                if ($days < 2) {
                    return; // jeda terlalu pendek, tidak ada waktu untuk warning
                }
                if ($ticket->resolved_at->addDays($days - 1)->isFuture()) {
                    return;
                }

                $ticket->update(['auto_close_warned_at' => now()]);

                $helpdesk->notifyUsers(
                    collect([$ticket->requester]),
                    $ticket,
                    'ticket_auto_close_warning',
                    'Ticket ' . $ticket->ticket_number . ' akan ditutup otomatis',
                    'Ticket Anda akan ditutup otomatis dalam 1 hari karena tidak ada balasan. '
                        . 'Balas ticket ini jika masalah belum terselesaikan.',
                    ['ticket_id' => $ticket->id]
                );
                $warned++;
            });

        Ticket::query()
            ->with(['company'])
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
                    ->where('company_id', $ticket->company_id)
                    ->whereIn('role', ['supervisor', 'admin'])
                    ->get();

                $metadata['escalated_at'] = now()->toDateTimeString();
                $ticket->update(['metadata' => $metadata]);

                $helpdesk->recordActivity($ticket, null, 'sla_escalated', 'Ticket di-escalate otomatis');
                $helpdesk->notifyUsers($supervisors, $ticket, 'sla_escalated', 'SLA ticket '.$ticket->ticket_number.' terlewati', 'Ticket membutuhkan perhatian karena SLA terlewati.', ['ticket_id' => $ticket->id]);
                $escalated++;
            });

        Ticket::query()
            ->where('status', 'resolved')
            ->with(['company', 'requester', 'assignee', 'team'])
            ->whereNotNull('resolved_at')
            ->get()
            ->each(function (Ticket $ticket) use (&$closed, $helpdesk): void {
                if ($ticket->resolved_at->addDays($ticket->company->auto_close_days)->isFuture()) {
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

        $this->info("Escalated: {$escalated}; Warned: {$warned}; Auto closed: {$closed}");

        return self::SUCCESS;
    }
}
