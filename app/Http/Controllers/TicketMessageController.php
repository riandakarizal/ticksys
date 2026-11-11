<?php

namespace App\Http\Controllers;

use App\Http\Requests\TicketMessage\TicketMessageStoreRequest;
use App\Models\Ticket;
use App\Support\Helpdesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/** Diskusi tiket — koordinasi teknisi, atasan, dan tim pusat. Tidak mengubah status. */
class TicketMessageController extends Controller
{
    public function store(TicketMessageStoreRequest $request, Ticket $ticket, Helpdesk $helpdesk): RedirectResponse
    {
        $user = $request->user();
        $body = $request->validated('body');
        $mentionedUsers = $helpdesk->extractMentionedUsers($body, $user);

        $message = $ticket->messages()->create([
            'user_id' => $user->id,
            'body' => $body,
            'is_internal' => false,
            'mentioned_user_ids' => $mentionedUsers->pluck('id')->all(),
        ]);

        $helpdesk->storeAttachments($ticket, $request->file('attachments', []), $user->id, 'lampiran', $message->id);
        $ticket->update(['last_reply_at' => now()]);
        $helpdesk->recordActivity($ticket, $user, 'reply_added', 'Komentar ditambahkan');

        $helpdesk->notifyUsers(
            $helpdesk->participants($ticket)->merge($mentionedUsers),
            $ticket,
            'ticket_reply',
            'Komentar baru pada ticket '.$ticket->ticket_number,
            $user->user_name.' menambahkan komentar.',
            [
                'ticket_id' => $ticket->id,
                'comment_body' => Str::limit(strip_tags($body), 180),
            ],
            $user,
        );

        return back()->with('success', 'Komentar terkirim.');
    }
}
