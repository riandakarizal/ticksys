<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Support\Helpdesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TicketMessageController extends Controller
{
    public function store(Request $request, Ticket $ticket, Helpdesk $helpdesk): RedirectResponse
    {
        abort_unless($helpdesk->visibleTickets(Auth::user())->whereKey($ticket->id)->exists(), 403);

        if ($ticket->isClosed()) {
            return back()->withErrors(['ticket' => 'Ticket yang sudah closed tidak bisa ditambahkan komentar lagi.']);
        }

        $user = Auth::user();
        $validated = $request->validate([
            'body' => ['required', 'string'],
            'is_internal' => ['nullable', 'boolean'],
            'attachments.*' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,txt,log,doc,docx,xls,xlsx,csv'],
        ]);

        $isInternal = (bool) ($validated['is_internal'] ?? false);
        abort_if($isInternal && $user->isClient(), 403);
        $beforeStatus = $ticket->status;

        $mentionedUsers = $helpdesk->extractMentionedUsers($validated['body'], $user);

        $message = $ticket->messages()->create([
            'user_id' => $user->id,
            'body' => $validated['body'],
            'is_internal' => $isInternal,
            'mentioned_user_ids' => $mentionedUsers->pluck('id')->all(),
        ]);

        $helpdesk->storeAttachments($ticket, $request->file('attachments', []), $user->id, $message->id);

        if (! $user->isClient() && ! $ticket->first_responded_at) {
            $ticket->first_responded_at = now();
        }

        $ticket->last_reply_at = now();

        if ($user->isClient() && in_array($ticket->status, ['pending', 'resolved'], true)) {
            $ticket->status = 'open';
            $ticket->resolved_at = null;
        }

        if (! $user->isClient() && $ticket->status === 'open') {
            $ticket->status = 'in_progress';
        }

        $ticket->save();

        $helpdesk->recordActivity($ticket, $user, $isInternal ? 'internal_note_added' : 'reply_added', $isInternal ? 'Internal note ditambahkan' : 'Balasan ditambahkan');

        $recipients = $isInternal
            ? $mentionedUsers
            : $helpdesk->participants($ticket)->merge($mentionedUsers);

        $helpdesk->notifyUsers(
            $recipients->reject(fn ($recipient) => $recipient->id === $user->id),
            $ticket,
            $isInternal ? 'internal_note' : 'ticket_reply',
            'Update pada ticket '.$ticket->ticket_number,
            $user->name.' menambahkan '.($isInternal ? 'catatan internal.' : 'balasan baru.'),
            [
                'ticket_id' => $ticket->id,
                'comment_body' => Str::limit(strip_tags($validated['body']), 180),
            ]
        );

        if (! $isInternal && $beforeStatus !== $ticket->status) {
            $statusMessage = $helpdesk->statusNotificationMessage($ticket->status);

            $helpdesk->notifyUsers(
                collect([$ticket->requester, $ticket->assignee])->filter()->reject(fn ($recipient) => $recipient->id === $user->id),
                $ticket,
                'ticket_status_changed',
                'Status ticket '.$ticket->ticket_number.' berubah',
                $statusMessage,
                [
                    'ticket_id' => $ticket->id,
                    'status' => $ticket->status,
                    'from_status' => $beforeStatus,
                ]
            );
        }

        return back()->with('success', $isInternal ? 'Internal note tersimpan.' : 'Balasan tersimpan.');
    }
}
