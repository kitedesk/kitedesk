<?php

namespace App\Domain\Webhooks\Listeners;

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Events\MessageCreated;
use App\Domain\Tickets\Events\TicketCreated;
use App\Domain\Tickets\Events\TicketUpdated;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Domain\Webhooks\Support\Webhooks;
use App\Http\Resources\Api\V1\MessageResource;
use App\Http\Resources\Api\V1\TicketResource;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Runs on the queue so the request never waits on webhook lookups and payload building.
 * `data` holds the same objects the REST API returns.
 */
class QueueWebhookDeliveries implements ShouldQueue
{
    public function handle(TicketCreated|TicketUpdated|MessageCreated $event): void
    {
        foreach ($this->webhookEventsFor($event) as $webhookEvent) {
            Webhooks::send($webhookEvent, fn (): array => $this->dataFor($event), $event instanceof TicketUpdated ? $event->changes : null);
        }
    }

    /**
     * @return list<WebhookEvent>
     */
    private function webhookEventsFor(TicketCreated|TicketUpdated|MessageCreated $event): array
    {
        return match (true) {
            $event instanceof TicketCreated => [WebhookEvent::TicketCreated],
            $event instanceof MessageCreated => [WebhookEvent::MessageCreated],
            ($event->changes['status']['to'] ?? null) === TicketStatus::Solved->value => [WebhookEvent::TicketUpdated, WebhookEvent::TicketSolved],
            default => [WebhookEvent::TicketUpdated],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function dataFor(TicketCreated|TicketUpdated|MessageCreated $event): array
    {
        return $event instanceof MessageCreated
            ? ['message' => (new MessageResource($event->message))->resolve()]
            : ['ticket' => (new TicketResource($event->ticket))->resolve()];
    }
}
