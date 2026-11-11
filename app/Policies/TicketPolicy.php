<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use App\Support\Helpdesk;

/**
 * Hak akses ticketing (route: semua role kecuali `fin`).
 *
 * | Aksi                      | Siapa                                                      |
 * |---------------------------|------------------------------------------------------------|
 * | lihat                     | lihat Helpdesk::visibleTickets()                           |
 * | buat, ubah detail         | teknisi (role user) & superadmin                           |
 * | solve, escalate (dr open) | teknisi (role user) & superadmin                           |
 * | return (dr escalated)     | admin/superadmin yang divisinya mencakup project tiket     |
 * | approve, reject (dr solved)| atasan langsung teknisi pembuat (user_parid) & superadmin |
 * | komentar                  | semua yang bisa melihat, kecuali vip (hanya-lihat)         |
 */
class TicketPolicy
{
    public function __construct(private readonly Helpdesk $helpdesk) {}

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isTicketTechnician();
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->visible($user, $ticket);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return ! $ticket->isClosed() && $this->create($user) && $this->visible($user, $ticket);
    }

    public function solve(User $user, Ticket $ticket): bool
    {
        return $ticket->status === 'open' && $this->create($user) && $this->visible($user, $ticket);
    }

    public function escalate(User $user, Ticket $ticket): bool
    {
        return $this->solve($user, $ticket);
    }

    public function return(User $user, Ticket $ticket): bool
    {
        if ($ticket->status !== 'escalated') {
            return false;
        }

        return $user->isSuperAdmin()
            || ($user->isAdmin() && $user->coversDivision($ticket->project?->pjct_div));
    }

    public function approve(User $user, Ticket $ticket): bool
    {
        if ($ticket->status !== 'solved') {
            return false;
        }

        return $user->isSuperAdmin()
            || ($ticket->creator?->user_parid !== null && $ticket->creator->user_parid === $user->id);
    }

    public function reject(User $user, Ticket $ticket): bool
    {
        return $this->approve($user, $ticket);
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return ! $ticket->isClosed() && ! $user->isVip() && $this->visible($user, $ticket);
    }

    public function download(User $user, Ticket $ticket): bool
    {
        return $this->visible($user, $ticket);
    }

    private function visible(User $user, Ticket $ticket): bool
    {
        if ($user->isSuperAdmin() || $user->isVip()) {
            return true;
        }

        return $this->helpdesk->visibleTickets($user)->whereKey($ticket->id)->exists();
    }
}
