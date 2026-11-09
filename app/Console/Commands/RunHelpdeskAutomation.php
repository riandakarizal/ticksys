<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Support\Helpdesk;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Notifikasi SLA terlewati untuk ticket aktif (open/escalated). Tidak ada auto-close:
 * ticket hanya bisa closed lewat approval atasan.
 */
#[Signature('app:run-helpdesk-automation')]
#[Description('Kirim notifikasi SLA terlewati untuk ticket aktif')]
class RunHelpdeskAutomation extends Command
{
    public function handle(Helpdesk $helpdesk): int
    {
        $notified = 0;

        Ticket::query()
            ->whereIn('status', Ticket::ACTIVE_STATUSES)
            ->where('resolution_due_at', '<', now())
            ->with(['creator.parent', 'project'])
            ->get()
            ->each(function (Ticket $ticket) use (&$notified, $helpdesk): void {
                $metadata = $ticket->metadata ?? [];

                if (! empty($metadata['sla_breach_notified_at']) || ! empty($metadata['escalated_at'])) {
                    return;
                }

                $metadata['sla_breach_notified_at'] = now()->toDateTimeString();
                $ticket->update(['metadata' => $metadata]);

                $helpdesk->recordActivity($ticket, null, 'sla_breached', 'Target penyelesaian (SLA) terlewati');
                $helpdesk->notifyUsers(
                    $helpdesk->divisionTeam($ticket)->push($helpdesk->approverFor($ticket)),
                    $ticket,
                    'sla_breached',
                    'SLA ticket '.$ticket->ticket_number.' terlewati',
                    'Ticket belum selesai melewati target penyelesaian dan membutuhkan perhatian.',
                    ['ticket_id' => $ticket->id],
                );
                $notified++;
            });

        $this->info("SLA breach notified: {$notified}");

        return self::SUCCESS;
    }
}
