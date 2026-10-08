<?php

namespace App\Domain\Tickets\Policies;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Ai\Support\AiSettings;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\SatisfactionSurvey;
use App\Models\User;

class TicketPolicy
{
    /**
     * Anyone signed in may list tickets; the `visibleTo` scope narrows what each person sees.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Requesters and people copied see their tickets; staff see what their role's ticket
     * access allows.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        return $ticket->isVisibleTo($user);
    }

    /**
     * Customers open their own requests; staff need the permission.
     */
    public function create(User $user): bool
    {
        return ! $user->isStaff() || $user->hasPermission(Permission::CreateTickets);
    }

    /**
     * Change ticket properties (status, priority, assignee, group, tags, fields).
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission(Permission::UpdateTickets)
            && $ticket->status !== TicketStatus::Closed
            && $ticket->isVisibleTo($user);
    }

    /**
     * Send a reply that the requester can see.
     */
    public function reply(User $user, Ticket $ticket): bool
    {
        if ($ticket->status === TicketStatus::Closed) {
            return false;
        }

        if ($user->isStaff()) {
            return $user->hasPermission(Permission::ReplyToTickets) && $ticket->isVisibleTo($user);
        }

        return $ticket->involves($user);
    }

    /**
     * Answer the satisfaction survey: the requester, once the ticket is solved.
     */
    public function rate(User $user, Ticket $ticket): bool
    {
        return SatisfactionSurvey::current()->canRate($ticket, $user);
    }

    /**
     * Add a private note only visible to staff.
     */
    public function addInternalNote(User $user, Ticket $ticket): bool
    {
        return $user->isStaff() && $ticket->status !== TicketStatus::Closed && $ticket->isVisibleTo($user);
    }

    /**
     * Merge a duplicate into another ticket: both must be editable and neither already merged.
     */
    public function merge(User $user, Ticket $source, Ticket $target): bool
    {
        return $user->hasPermission(Permission::MergeTickets)
            && $this->update($user, $source)
            && $this->update($user, $target)
            && $source->merged_into_id === null
            && $target->merged_into_id === null;
    }

    /**
     * Link or unlink two related tickets. It changes both, and uses the same permission as merging.
     */
    public function link(User $user, Ticket $ticket, Ticket $linked): bool
    {
        return $user->hasPermission(Permission::MergeTickets)
            && $this->update($user, $ticket)
            && $this->update($user, $linked);
    }

    /**
     * Hand the ticket to another agent or group.
     */
    public function forward(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission(Permission::ForwardTickets) && $this->update($user, $ticket);
    }

    /**
     * Run a manual workflow on the ticket.
     */
    public function runWorkflows(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission(Permission::RunWorkflows) && $this->update($user, $ticket);
    }

    /**
     * Request a secret from the customer or share one with them. Both reach the customer,
     * so it takes the right to reply as well.
     */
    public function useSecrets(User $user, Ticket $ticket): bool
    {
        return $user->isStaff() && $user->hasPermission(Permission::UseSecrets) && $this->reply($user, $ticket);
    }

    /**
     * Use the AI assistant on the ticket (summaries, drafts). It reads the whole conversation,
     * so viewing is enough; the assistant only fills the composer, sending still takes `reply`.
     */
    public function useAi(User $user, Ticket $ticket): bool
    {
        return $user->isStaff()
            && $user->hasPermission(Permission::UseAi)
            && AiSettings::current()->isAvailable()
            && $this->view($user, $ticket);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission(Permission::DeleteTickets) && $ticket->isVisibleTo($user);
    }
}
