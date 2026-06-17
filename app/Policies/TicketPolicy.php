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
        if ($user->isClient()) {
            return false;
        }

        return $this->visible($user, $ticket) && ! $ticket->isClosed();
    }

    public function merge(User $user, Ticket $ticket): bool
    {
        if ($user->isClient()) {
            return false;
        }

        return $this->visible($user, $ticket) && ! $ticket->isClosed();
    }

    public function split(User $user, Ticket $ticket): bool
    {
        if ($user->isClient()) {
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
        if ($user->isAdmin()) {
            return $ticket->company_id === $user->company_id;
        }

        if ($ticket->company_id !== $user->company_id) {
            return false;
        }

        // Bug 4 fix: gunakan query langsung agar tidak bergantung pada eager load
        // Bug 5 fix: agent bisa lihat semua tiket di team-nya, termasuk yang belum di-assign
        if ($user->isSupervisor() || $user->isAgent()) {
            return $ticket->team_id !== null
                && $user->teams()->where('teams.id', $ticket->team_id)->exists();
        }

        return $ticket->requester_id === $user->id && $user->isClient();
    }
}
