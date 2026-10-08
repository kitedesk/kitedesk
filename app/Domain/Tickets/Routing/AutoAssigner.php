<?php

namespace App\Domain\Tickets\Routing;

use App\Domain\Accounts\Enums\AssignmentMode;
use App\Domain\Accounts\Models\Group;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hands unassigned tickets to an agent of their group, following the group's assignment mode.
 * Only full agents (admins and agents) who are not away are considered.
 */
class AutoAssigner
{
    /**
     * Assign the (possibly unsaved) ticket when its group asks for it. Returns the chosen agent.
     */
    public function assign(Ticket $ticket): ?User
    {
        if ($ticket->assignee_id !== null || $ticket->group_id === null) {
            return null;
        }

        $group = Group::query()->find($ticket->group_id);

        $agent = match ($group?->assignment_mode) {
            AssignmentMode::RoundRobin => $this->takeTurn($group->id),
            AssignmentMode::LeastBusy => $this->leastBusy($group),
            default => null,
        };

        if ($agent === null) {
            return null;
        }

        $ticket->assignee_id = $agent->id;

        if ($ticket->status === TicketStatus::New) {
            $ticket->status = TicketStatus::Open;
        }

        return $agent;
    }

    /**
     * The next agent in the group's rotation. The group row is locked only while the turn
     * is taken, so concurrent tickets go to different agents.
     */
    private function takeTurn(int $groupId): ?User
    {
        return DB::transaction(function () use ($groupId): ?User {
            $group = Group::query()->lockForUpdate()->find($groupId);
            $agent = $group !== null ? $this->nextInRotation($group) : null;

            if ($group !== null && $agent !== null) {
                $group->forceFill(['last_assigned_user_id' => $agent->id])->saveQuietly();
            }

            return $agent;
        });
    }

    /**
     * @return Builder<User>
     */
    private function candidates(Group $group): Builder
    {
        return $group->agents()
            ->getQuery()
            ->assignable()
            ->where('is_available', true)
            ->orderBy('users.id');
    }

    private function nextInRotation(Group $group): ?User
    {
        /** @var Collection<int, User> $agents */
        $agents = $this->candidates($group)->get();

        return $agents->first(fn (User $agent): bool => $agent->id > (int) $group->last_assigned_user_id)
            ?? $agents->first();
    }

    private function leastBusy(Group $group): ?User
    {
        return $this->candidates($group)
            ->withCount(['assignedTickets as open_tickets_count' => fn (Builder $tickets) => $tickets->whereIn('status', TicketStatus::unresolved())])
            ->reorder()
            ->orderBy('open_tickets_count')
            ->orderBy('users.id')
            ->first();
    }
}
