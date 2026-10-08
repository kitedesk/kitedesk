<?php

namespace App\Domain\Tickets\Actions;

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Events\TicketRefreshed;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Domain\Webhooks\Support\Webhooks;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Merges a duplicate ticket into another: the conversation moves over, the duplicate's
 * people are copied on the target, and the duplicate is closed with a pointer to it.
 */
class MergeTickets
{
    public function __construct(private UpdateTicket $updateTicket, private AddMessage $addMessage) {}

    public function handle(Ticket $source, Ticket $target, User $actor): Ticket
    {
        $target = DB::transaction(function () use ($source, $target, $actor): Ticket {
            // Attachments belong to the messages, so they move along with them.
            TicketMessage::query()->where('ticket_id', $source->id)->update(['ticket_id' => $target->id]);

            $this->updateTicket->handle($target, [
                'collaborator_ids' => array_values(array_unique([
                    ...$target->collaboratorIds(),
                    ...$source->collaboratorIds(),
                    $source->requester_id,
                ])),
                'tags' => array_values(array_unique([
                    ...$target->tags()->pluck('name')->map(strval(...))->all(),
                    ...$source->tags()->pluck('name')->map(strval(...))->all(),
                ])),
            ], $actor);

            $this->addMessage->handle(
                $target,
                $actor,
                '<p>'.e(__('Ticket :source ":subject" was merged into this ticket.', ['source' => $source->reference(), 'subject' => $source->subject])).'</p>',
                isInternal: true,
            );

            $source->forceFill(['merged_into_id' => $target->id])->save();

            $this->addMessage->handle(
                $source,
                $actor,
                '<p>'.e(__('Merged into ticket :target. Continue the conversation there.', ['target' => $target->reference()])).'</p>',
                isInternal: true,
            );

            $this->updateTicket->handle($source, ['status' => TicketStatus::Closed], $actor);

            // The moved messages were a bulk query, so nothing has told the target's viewers yet.
            TicketRefreshed::dispatch($target);

            return $target->refresh();
        });

        Webhooks::send(WebhookEvent::TicketMerged, fn (): array => [
            'ticket' => (new TicketResource($source->refresh()))->resolve(),
            'target' => (new TicketResource($target))->resolve(),
        ]);

        return $target;
    }
}
