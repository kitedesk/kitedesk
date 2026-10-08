<?php

use App\Domain\Tickets\Actions\CreateTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;

/**
 * A ticket created the way customers create them, so ticket events (and workflows) fire.
 *
 * @param  array<string, mixed>  $attributes
 */
function workflowTicket(array $attributes = [], ?User $requester = null): Ticket
{
    return app(CreateTicket::class)->handle(
        $requester ?? User::factory()->create(),
        ['subject' => 'Printer on fire', 'body' => '<p>Please help</p>', ...$attributes],
        TicketChannel::Portal,
    );
}

/**
 * A condition group with a single condition.
 *
 * @return array{match: string, conditions: list<array{field: string, operator: string, value: string}>}
 */
function workflowCondition(string $field, string $operator, string $value = ''): array
{
    return ['match' => 'all', 'conditions' => [['field' => $field, 'operator' => $operator, 'value' => $value]]];
}
