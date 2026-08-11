<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TicketPolicy
{
    use HandlesAuthorization;

    public function create(User $user): bool
    {
        return ! $user->isVip();
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->visible($user, $ticket);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->isUser() || $user->isVip()) {
            return false;
        }

        return $this->visible($user, $ticket) && ! $ticket->isClosed();
    }

    public function merge(User $user, Ticket $ticket): bool
    {
        if (! ($user->isSuperAdmin() || $user->isAdmin())) {
            return false;
        }

        return $this->visible($user, $ticket) && ! $ticket->isClosed();
    }

    public function split(User $user, Ticket $ticket): bool
    {
        if (! ($user->isSuperAdmin() || $user->isAdmin())) {
            return false;
        }

        return $this->visible($user, $ticket) && ! $ticket->isClosed();
    }

    public function download(User $user, Ticket $ticket): bool
    {
        return $this->visible($user, $ticket);
    }

    public function createMessage(User $user, Ticket $ticket): bool
    {
        return $this->visible($user, $ticket) && ! $ticket->isClosed();
    }

    private function visible(User $user, Ticket $ticket): bool
    {
        // Tiket tidak lagi terikat ke Team (lihat TicketManager::createTicket) — jadi
        // visibilitas staff tidak lagi disaring lewat keanggotaan team_user.
        if ($user->isSuperAdmin() || $user->isVip() || $user->isAdmin()) {
            return true;
        }

        if ($user->isSiteAdmin()) {
            return $ticket->assigned_to === $user->id;
        }

        return $ticket->requester_id === $user->id && $user->isUser();
    }
}
