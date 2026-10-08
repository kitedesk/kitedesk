<?php

namespace App\Domain\Tickets\Actions;

use App\Domain\Accounts\Models\Group;
use App\Domain\Support\RichText;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Notifications\TicketForwarded;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Hands a ticket over to another agent or to a group, with an optional internal note
 * explaining why.
 *
 * Forwarding to an agent assigns it to them. Forwarding to a group moves the ticket to
 * that group's queue unassigned, so the group's assignment mode may pick an agent. The
 * agent who receives it is notified, or every member of the group when nobody was picked.
 */
class ForwardTicket
{
    public function __construct(private UpdateTicket $updateTicket, private AddMessage $addMessage) {}

    public function handle(Ticket $ticket, User|Group $target, User $actor, string $note = ''): Ticket
    {
        $note = RichText::sanitize($note);

        $ticket = DB::transaction(function () use ($ticket, $target, $actor, $note): Ticket {
            if ($target instanceof Group) {
                $ticket->assignee_id = null;
                $this->updateTicket->handle($ticket, ['group_id' => $target->id], $actor, notifyAssignee: false);
            } else {
                $this->updateTicket->handle($ticket, ['assignee_id' => $target->id], $actor, notifyAssignee: false);
            }

            if (! RichText::isBlank($note)) {
                $this->addMessage->handle($ticket, $actor, $note, isInternal: true, metadata: [
                    'forwarded_to' => [
                        'type' => $target instanceof Group ? 'group' : 'agent',
                        'id' => $target->id,
                        'name' => $target->name,
                    ],
                ]);
            }

            return $ticket;
        });

        Notification::send(
            $this->recipients($ticket, $target, $actor),
            new TicketForwarded($ticket, $actor, $target instanceof Group ? $target->name : null, $note),
        );

        return $ticket;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Ticket $ticket, User|Group $target, User $actor): Collection
    {
        $recipients = match (true) {
            $target instanceof User => collect([$target]),
            $ticket->assignee_id !== null => collect([$ticket->assignee]),
            default => $target->agents()->staff()->get()->toBase(),
        };

        return $recipients
            ->filter()
            ->reject(fn (User $user): bool => $user->is($actor))
            ->values();
    }
}
