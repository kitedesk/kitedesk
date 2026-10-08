<?php

use App\Domain\Accounts\Models\Group;
use App\Domain\Accounts\Models\Organization;
use App\Domain\Tickets\Actions\CreateTicket;
use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Models\RoutingRule;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Models\TicketForm;
use App\Models\User;

beforeEach(function () {
    $this->billing = Group::factory()->create(['name' => 'Billing']);
    $this->support = Group::factory()->create(['name' => 'Support']);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function routedTicket(array $attributes = [], ?User $requester = null): Ticket
{
    return app(CreateTicket::class)->handle(
        $requester ?? User::factory()->create(),
        ['subject' => 'Question', 'body' => '<p>Hi</p>', ...$attributes],
        TicketChannel::Portal,
    );
}

test('the first matching active rule picks the group, priority and tags', function () {
    $billingCategory = TicketCategory::factory()->create();
    RoutingRule::factory()->matching([['field' => 'category', 'operator' => 'is', 'value' => (string) $billingCategory->id]])
        ->routesTo($this->support)->create(['position' => 0, 'is_active' => false]);
    RoutingRule::factory()->matching([['field' => 'category', 'operator' => 'is', 'value' => (string) $billingCategory->id]])
        ->routesTo($this->billing, 'high', ['billing'])->create(['position' => 1]);
    RoutingRule::factory()->routesTo($this->support)->create(['position' => 2]);

    $ticket = routedTicket(['category_id' => $billingCategory->id, 'tags' => ['vip']]);

    expect($ticket->group_id)->toBe($this->billing->id)
        ->and($ticket->priority)->toBe(TicketPriority::High)
        ->and($ticket->tags->pluck('name')->sort()->values()->all())->toBe(['billing', 'vip']);

    expect(routedTicket()->group_id)->toBe($this->support->id);
});

test('a category condition also matches its subcategories', function () {
    $billing = TicketCategory::factory()->create();
    $refunds = TicketCategory::factory()->childOf($billing)->create();
    $other = TicketCategory::factory()->create();
    RoutingRule::factory()->matching([['field' => 'category', 'operator' => 'is', 'value' => (string) $billing->id]])
        ->routesTo($this->billing)->create();
    RoutingRule::factory()->matching([['field' => 'category', 'operator' => 'is_not', 'value' => (string) $billing->id]])
        ->routesTo($this->support)->create(['position' => 1]);

    expect(routedTicket(['category_id' => $refunds->id])->group_id)->toBe($this->billing->id)
        ->and(routedTicket(['category_id' => $other->id])->group_id)->toBe($this->support->id);
});

test('rules can match any condition, including custom fields and the requester', function () {
    TicketField::factory()->create(['key' => 'order_number']);
    $acme = Organization::factory()->create(['domains' => ['acme.test']]);
    RoutingRule::factory()->matching([
        ['field' => 'custom_fields.order_number', 'operator' => 'contains', 'value' => 'INV'],
        ['field' => 'organization', 'operator' => 'is', 'value' => (string) $acme->id],
    ], match: 'any')->routesTo($this->billing)->create();

    expect(routedTicket(['custom_fields' => ['order_number' => 'inv-2001']])->group_id)->toBe($this->billing->id)
        ->and(routedTicket(requester: User::factory()->create(['email' => 'ana@acme.test']))->group_id)->toBe($this->billing->id)
        ->and(routedTicket(['custom_fields' => ['order_number' => '2001']])->group_id)->toBeNull();
});

test('an explicitly chosen group or priority is not overridden', function () {
    RoutingRule::factory()->routesTo($this->billing, 'urgent')->create();

    $withGroup = routedTicket(['group_id' => $this->support->id]);
    $withPriority = routedTicket(['priority' => 'low']);

    expect($withGroup->group_id)->toBe($this->support->id)
        ->and($withGroup->priority)->toBe(TicketPriority::Normal)
        ->and($withPriority->group_id)->toBe($this->billing->id)
        ->and($withPriority->priority)->toBe(TicketPriority::Low);
});

test('changing the category switches the form and routes the ticket again', function () {
    $default = TicketForm::factory()->default()->create();
    $billingForm = TicketForm::factory()->create();
    $billing = TicketCategory::factory()->create(['ticket_form_id' => $billingForm->id]);
    $refunds = TicketCategory::factory()->childOf($billing)->create();
    RoutingRule::factory()->matching([['field' => 'category', 'operator' => 'is', 'value' => (string) $billing->id]])
        ->routesTo($this->billing, tags: ['refund'])->create();

    $ticket = routedTicket();
    expect($ticket->ticket_form_id)->toBe($default->id)->and($ticket->group_id)->toBeNull();

    app(UpdateTicket::class)->handle($ticket, ['category_id' => $refunds->id]);

    expect($ticket->refresh()->ticket_form_id)->toBe($billingForm->id)
        ->and($ticket->group_id)->toBe($this->billing->id)
        ->and($ticket->tags->pluck('name')->all())->toBe(['refund']);

    $ticket = routedTicket();
    app(UpdateTicket::class)->handle($ticket, ['category_id' => $refunds->id, 'group_id' => $this->support->id]);

    expect($ticket->refresh()->group_id)->toBe($this->support->id);
});
