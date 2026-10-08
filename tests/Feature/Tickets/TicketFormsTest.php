<?php

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Models\TicketForm;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->customer = User::factory()->create();

    $this->orderNumber = TicketField::factory()->create(['key' => 'order_number']);
    $this->internalCode = TicketField::factory()->agentOnly()->create(['key' => 'internal_code']);
    $this->device = TicketField::factory()->select(['iOS', 'Android'])->create(['key' => 'device']);

    $this->billingForm = TicketForm::factory()
        ->withFields([$this->orderNumber, $this->internalCode], required: [$this->orderNumber->id, $this->internalCode->id])
        ->create();
    $this->defaultForm = TicketForm::factory()->default()->withFields([$this->device])->create();

    $this->billing = TicketCategory::factory()->create(['ticket_form_id' => $this->billingForm->id]);
    $this->refunds = TicketCategory::factory()->childOf($this->billing)->create();
    $this->general = TicketCategory::factory()->create();
});

/**
 * @param  array<string, mixed>  $data
 */
function submitRequest(User $customer, array $data): TestResponse
{
    return test()->actingAs($customer)->post(route('portal.tickets.store'), [
        'subject' => 'Refund please',
        'body' => '<p>I was charged twice.</p>',
        ...$data,
    ]);
}

test('a subcategory uses its parent form and enforces its required customer fields', function () {
    submitRequest($this->customer, ['category_id' => $this->refunds->id])
        ->assertSessionHasErrors('custom_fields.order_number')
        ->assertSessionDoesntHaveErrors('custom_fields.internal_code');

    submitRequest($this->customer, ['category_id' => $this->refunds->id, 'custom_fields' => ['order_number' => 'INV-1']])
        ->assertSessionHasNoErrors();

    $ticket = Ticket::query()->sole();
    expect($ticket->category_id)->toBe($this->refunds->id)
        ->and($ticket->ticket_form_id)->toBe($this->billingForm->id)
        ->and($ticket->custom_fields)->toBe(['order_number' => 'INV-1']);
});

test('customers cannot fill agent-only fields or fields of another form', function () {
    submitRequest($this->customer, [
        'category_id' => $this->refunds->id,
        'custom_fields' => ['order_number' => 'INV-1', 'internal_code' => 'X'],
    ])->assertSessionHasErrors('custom_fields');

    submitRequest($this->customer, [
        'category_id' => $this->refunds->id,
        'custom_fields' => ['order_number' => 'INV-1', 'device' => 'iOS'],
    ])->assertSessionHasErrors('custom_fields');

    expect(Ticket::query()->count())->toBe(0);
});

test('categories without a form use the default form', function () {
    submitRequest($this->customer, ['category_id' => $this->general->id, 'custom_fields' => ['device' => 'Windows']])
        ->assertSessionHasErrors('custom_fields.device');

    submitRequest($this->customer, ['category_id' => $this->general->id, 'custom_fields' => ['device' => 'iOS']])
        ->assertSessionHasNoErrors();

    expect(Ticket::query()->sole()->ticket_form_id)->toBe($this->defaultForm->id);
});

test('customers must pick a visible, active category and narrow it to a subcategory', function () {
    $hidden = TicketCategory::factory()->agentOnly()->create();
    $inactive = TicketCategory::factory()->inactive()->create();

    submitRequest($this->customer, [])->assertSessionHasErrors('category_id');
    submitRequest($this->customer, ['category_id' => $this->billing->id])->assertSessionHasErrors('category_id');
    submitRequest($this->customer, ['category_id' => $hidden->id])->assertSessionHasErrors('category_id');
    submitRequest($this->customer, ['category_id' => $inactive->id])->assertSessionHasErrors('category_id');

    expect(Ticket::query()->count())->toBe(0);
});

test('the request form only offers what customers may see', function () {
    TicketCategory::factory()->agentOnly()->create();

    $this->actingAs($this->customer)
        ->get(route('portal.tickets.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/tickets/create')
            ->has('categories', 2)
            ->where('categories.0.id', $this->billing->id)
            ->where('categories.0.children.0.form_id', $this->billingForm->id)
            ->where('categories.1.form_id', $this->defaultForm->id)
            ->has("forms.{$this->billingForm->id}", 1)
            ->where("forms.{$this->billingForm->id}.0.key", 'order_number')
            ->where("forms.{$this->billingForm->id}.0.is_required", true)
            ->where('defaultFormId', $this->defaultForm->id));
});

test('agents may create uncategorized tickets with the default form', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)->post(route('agent.tickets.store'), [
        'requester_id' => $this->customer->id,
        'subject' => 'Phone call',
        'body' => '<p>Called about their device.</p>',
        'custom_fields' => ['device' => 'Android'],
    ])->assertSessionHasNoErrors();

    $ticket = Ticket::query()->sole();
    expect($ticket->category_id)->toBeNull()
        ->and($ticket->ticket_form_id)->toBe($this->defaultForm->id);
});

test('the ticket panel shows the form fields plus any other field with a value', function () {
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->create([
        'category_id' => $this->billing->id,
        'ticket_form_id' => $this->billingForm->id,
        'custom_fields' => ['device' => 'iOS'],
    ]);

    $this->actingAs($agent)
        ->get(route('agent.tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ticketFields.0.key', 'order_number')
            ->where('ticketFields.1.key', 'internal_code')
            ->where('ticketFields.2.key', 'device')
            ->where('ticketFields.2.options', ['iOS', 'Android'])
            ->where('ticket.category.name', $this->billing->name));
});
