<?php

namespace App\Http\Controllers;

use App\Http\Requests\TicketMessage\TicketMessageStoreRequest;
use App\Models\Ticket;
use App\Support\Helpdesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TicketMessageController extends Controller
{
    public function store(TicketMessageStoreRequest $request, Ticket $ticket, Helpdesk $helpdesk): RedirectResponse
    {
        $this->authorize('createMessage', $ticket);

        if ($ticket->isClosed()) {
            return back()->withErrors(['ticket' => 'Comments cannot be added to a closed ticket.']);
        }

        $user        = Auth::user();
        $validated   = $request->validated();
        $isInternal  = (bool) ($validated['is_internal'] ?? false);

        abort_if($isInternal && $user->isClient(), 403);

        $beforeStatus   = $ticket->status;
        $mentionedUsers = $helpdesk->extractMentionedUsers($validated['body'], $user);

        $message = $ticket->messages()->create([
            'user_id'            => $user->id,
            'body'               => $validated['body'],
            'is_internal'        => $isInternal,
            'mentioned_user_ids' => $mentionedUsers->pluck('id')->all(),
        ]);

        $helpdesk->storeAttachments($ticket, $request->file('attachments', []), $user->id, $message->id);

        if (! $user->isClient() && ! $ticket->first_responded_at) {
            $ticket->first_responded_at = now();
        }

        $ticket->last_reply_at = now();

        // Auto-transition: client reply reopens a pending/resolved ticket.
        if ($user->isClient() && in_array($ticket->status, ['pending', 'resolved'], true)) {
            $ticket->status              = 'open';
            $ticket->resolved_at         = null;
            $ticket->auto_close_warned_at = null;
        }

        // Auto-transition: first agent/staff reply moves ticket to in_progress.
        if (! $user->isClient() && $ticket->status === 'open') {
            $ticket->status = 'in_progress';
        }

        $ticket->save();

        $helpdesk->recordActivity(
            $ticket,
            $user,
            $isInternal ? 'internal_note_added' : 'reply_added',
            $isInternal ? 'Internal note added' : 'Reply added'
        );

        // Log the automatic status transition separately so the audit trail is clear.
        if ($beforeStatus !== $ticket->status) {
            $description = 'Status ticket berubah dari ' . Str::headline($beforeStatus) . ' ke ' . Str::headline($ticket->status);
            $helpdesk->recordActivity($ticket, $user, 'ticket_status_changed', $description, [
                'from' => $beforeStatus,
                'to'   => $ticket->status,
            ]);
        }

        $recipients = $isInternal
            ? $mentionedUsers
            : $helpdesk->participants($ticket)->merge($mentionedUsers);

        $helpdesk->notifyUsers(
            $recipients->reject(fn ($recipient) => $recipient->id === $user->id),
            $ticket,
            $isInternal ? 'internal_note' : 'ticket_reply',
            'Update pada ticket ' . $ticket->ticket_number,
            $user->name . ' menambahkan ' . ($isInternal ? 'catatan internal.' : 'balasan baru.'),
            [
                'ticket_id'    => $ticket->id,
                'comment_body' => Str::limit(strip_tags($validated['body']), 180),
            ]
        );

        if (! $isInternal && $beforeStatus !== $ticket->status) {
            $helpdesk->notifyUsers(
                collect([$ticket->requester, $ticket->assignee])
                    ->filter()
                    ->reject(fn ($recipient) => $recipient->id === $user->id),
                $ticket,
                'ticket_status_changed',
                'Status ticket ' . $ticket->ticket_number . ' berubah',
                $helpdesk->statusNotificationMessage($ticket->status),
                [
                    'ticket_id'   => $ticket->id,
                    'status'      => $ticket->status,
                    'from_status' => $beforeStatus,
                ]
            );
        }

        return back()->with('success', $isInternal ? 'Internal note saved.' : 'Reply sent.');
    }
}
