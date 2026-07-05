<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TicketPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->visible($user, $ticket);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->isClient() || $user->isVip()) {
            return false;
        }

        return $this->visible($user, $ticket) && ! $ticket->isClosed();
    }

    public function merge(User $user, Ticket $ticket): bool
    {
        if ($user->isClient() || $user->isVip()) {
            return false;
        }

        return $this->visible($user, $ticket) && ! $ticket->isClosed();
    }

    public function split(User $user, Ticket $ticket): bool
    {
        if ($user->isClient() || $user->isVip()) {
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
        if ($user->isAdmin() || $user->isVip()) {
            return true;
        }

        if ($user->isSupervisor() || $user->isAgent()) {
            return $ticket->team_id !== null
                && $user->teams()->where('teams.id', $ticket->team_id)->exists();
        }

        return $ticket->requester_id === $user->id && $user->isClient();
    }
}
