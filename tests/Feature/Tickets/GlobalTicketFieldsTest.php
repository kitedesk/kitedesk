<?php

use App\Domain\Ai\Mcp\KiteDeskServer;
use App\Domain\Ai\Mcp\McpAccess;
use App\Domain\Ai\Mcp\Tools\GetTicketTool;
use App\Domain\Ai\Mcp\Tools\SearchTicketsTool;
use App\Domain\Ai\Mcp\Tools\UpdateTicketTool;
use App\Domain\Ai\Support\AiSettings;
use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Tickets\Models\SavedView;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Models\TicketForm;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->agent = User::factory()->agent()->create();
    $this->customer = User::factory()->create();

    $this->plan = TicketField::factory()->select(['Free', 'Pro'])->global(required: true)->create(['key' => 'plan', 'label' => 'Plan']);
    $this->modules = TicketField::factory()->multiSelect(['Billing', 'Mail', 'Chat'])->global()->forTypes(TicketType::Incident)->create(['key' => 'modules', 'label' => 'Modules']);
    $this->vip = TicketField::factory()->global()->create(['key' => 'vip', 'type' => 'checkbox']);
});

test('global fields show on tickets without a form, and type-limited ones follow the ticket type', function () {
    $ticket = Ticket::factory()->create(['type' => TicketType::Question]);

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->has('ticketFields', 2)
            ->where('ticketFields.0.key', 'plan')
            ->where('ticketFields.0.is_required', true)
            ->where('ticketFields.1.key', 'vip'));

    $ticket->update(['type' => TicketType::Incident]);

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->has('ticketFields', 3)
            ->where('ticketFields.1.key', 'modules'));
});

test('a global field also shows next to the fields of the ticket form', function () {
    $orderNumber = TicketField::factory()->create(['key' => 'order_number']);
    $form = TicketForm::factory()->withFields([$orderNumber])->create();
    $ticket = Ticket::factory()->create(['ticket_form_id' => $form->id]);

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ticketFields.0.key', 'order_number')
            ->where('ticketFields.1.key', 'plan')
            ->where('ticketFields.2.key', 'vip'));
});

test('new tickets need required global fields, and multi-selects only take their options', function () {
    $ticket = fn (array $data) => $this->actingAs($this->agent)->post(route('agent.tickets.store'), [
        'requester_id' => $this->customer->id,
        'subject' => 'Outage',
        'body' => '<p>Mail is down.</p>',
        'type' => TicketType::Incident->value,
        ...$data,
    ]);

    $ticket(['custom_fields' => ['modules' => ['Mail']]])->assertSessionHasErrors('custom_fields.plan');
    $ticket(['custom_fields' => ['plan' => 'Pro', 'modules' => ['Fax']]])->assertSessionHasErrors('custom_fields.modules.0');
    $ticket(['custom_fields' => ['plan' => 'Pro', 'modules' => ['Mail', 'Chat'], 'vip' => '1']])->assertSessionHasNoErrors();

    expect(Ticket::query()->sole()->custom_fields)->toBe(['plan' => 'Pro', 'modules' => ['Mail', 'Chat'], 'vip' => true]);
});

test('the customer request form offers global fields, except those limited to some types', function () {
    $this->actingAs($this->customer)
        ->get(route('portal.tickets.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('globalFields', 2)
            ->where('globalFields.0.key', 'plan')
            ->where('globalFields.1.key', 'vip'));

    $this->actingAs($this->customer)->post(route('portal.tickets.store'), [
        'subject' => 'Question',
        'body' => '<p>How do I upgrade?</p>',
        'custom_fields' => ['vip' => '1'],
    ])->assertSessionHasErrors('custom_fields.plan');

    $this->actingAs($this->customer)->post(route('portal.tickets.store'), [
        'subject' => 'Question',
        'body' => '<p>How do I upgrade?</p>',
        'custom_fields' => ['plan' => 'Free', 'modules' => ['Mail']],
    ])->assertSessionHasErrors('custom_fields');
});

test('agents fill required fields before solving, by update or by reply, but workflows are not held back', function () {
    $ticket = Ticket::factory()->status(TicketStatus::Open)->create();

    $this->actingAs($this->agent)
        ->patch(route('agent.tickets.update', $ticket), ['status' => TicketStatus::Solved->value])
        ->assertSessionHasErrors('custom_fields.plan');

    $this->actingAs($this->agent)
        ->post(route('agent.tickets.messages.store', $ticket), ['body' => '<p>Fixed.</p>', 'status' => TicketStatus::Solved->value])
        ->assertSessionHasErrors('custom_fields.plan');

    expect($ticket->refresh()->status)->toBe(TicketStatus::Open);

    $this->actingAs($this->agent)
        ->patch(route('agent.tickets.update', $ticket), ['status' => TicketStatus::Solved->value, 'custom_fields' => ['plan' => 'Free']])
        ->assertSessionHasNoErrors();

    expect($ticket->refresh()->status)->toBe(TicketStatus::Solved);

    $other = Ticket::factory()->status(TicketStatus::Open)->create();
    app(UpdateTicket::class)->handle($other, ['status' => TicketStatus::Solved]);

    expect($other->refresh()->status)->toBe(TicketStatus::Solved);
});

test('custom field changes are recorded in the ticket activity', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs($this->agent)
        ->patch(route('agent.tickets.update', $ticket), ['custom_fields' => ['plan' => 'Pro']])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('activity.0.changes.new.custom_fields', ['plan' => 'Pro'])));
});

test('the ticket list filters by drop-down, multi-select and checkbox fields, and saved views keep the filter', function () {
    $pro = Ticket::factory()->create(['custom_fields' => ['plan' => 'Pro', 'modules' => ['Mail', 'Chat'], 'vip' => true]]);
    $free = Ticket::factory()->create(['custom_fields' => ['plan' => 'Free', 'modules' => ['Billing']]]);
    $blank = Ticket::factory()->create();

    $ids = function (array $filter) {
        $ids = [];
        $this->actingAs($this->agent)
            ->get(route('agent.tickets.index', ['view' => 'all', 'filter' => $filter]))
            ->assertInertia(function (Assert $page) use (&$ids) {
                $ids = collect($page->toArray()['props']['tickets']['data'])->pluck('id')->sort()->values()->all();
            });

        return $ids;
    };

    expect($ids(['field_plan' => 'Pro']))->toBe([$pro->id])
        ->and($ids(['field_modules' => 'Chat,Billing']))->toBe([$pro->id, $free->id])
        ->and($ids(['field_vip' => 'true']))->toBe([$pro->id])
        ->and($ids(['field_vip' => 'false']))->toBe([$free->id, $blank->id]);

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.index', ['view' => 'all', 'filter' => ['field_unknown' => 'x']]))
        ->assertBadRequest();

    $this->actingAs($this->agent)->post(route('agent.views.store'), [
        'name' => 'Pro customers',
        'view' => 'all',
        'filter' => ['field_plan' => 'Pro', 'field_unknown' => 'x'],
    ])->assertSessionHasNoErrors();

    $view = SavedView::query()->sole();
    expect($view->filters)->toBe(['view' => 'all', 'field_plan' => 'Pro']);

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.index', ['view' => $view->key()]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $pro->id));
});

test('the API filters tickets by custom fields and holds solving to required fields', function () {
    Sanctum::actingAs($this->agent, ['tickets:read', 'tickets:write']);
    $pro = Ticket::factory()->status(TicketStatus::Open)->create(['custom_fields' => ['modules' => ['Mail']]]);
    Ticket::factory()->create(['custom_fields' => ['modules' => ['Chat']]]);

    $this->getJson(route('api.v1.tickets.index', ['filter' => ['field_modules' => 'Mail']]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $pro->id);

    $this->patchJson(route('api.v1.tickets.update', $pro), ['status' => 'solved'])
        ->assertJsonValidationErrors('custom_fields.plan');
});

test('MCP clients read, set and search custom fields', function () {
    (new AiSettings(mcpEnabled: true))->save();
    request()->attributes->set(McpAccess::OAUTH, true);
    $ticket = Ticket::factory()->create(['type' => TicketType::Incident]);

    KiteDeskServer::actingAs($this->agent)
        ->tool(GetTicketTool::class, ['ticket' => $ticket->reference()])
        ->assertOk()
        ->assertSee('Plan (plan, select, options: Free | Pro, required): (empty)')
        ->assertSee('Modules (modules, multiselect, options: Billing | Mail | Chat): (empty)');

    KiteDeskServer::actingAs($this->agent)
        ->tool(UpdateTicketTool::class, ['ticket' => $ticket->reference(), 'custom_fields' => ['plan' => 'Gold']])
        ->assertHasErrors();

    KiteDeskServer::actingAs($this->agent)
        ->tool(UpdateTicketTool::class, ['ticket' => $ticket->reference(), 'custom_fields' => ['plan' => 'Pro', 'modules' => ['Mail']]])
        ->assertOk();

    expect($ticket->refresh()->custom_fields)->toBe(['plan' => 'Pro', 'modules' => ['Mail']]);

    Ticket::factory()->create(['custom_fields' => ['plan' => 'Free']]);

    KiteDeskServer::actingAs($this->agent)
        ->tool(SearchTicketsTool::class, ['custom_fields' => ['plan' => 'Pro']])
        ->assertOk()
        ->assertSee($ticket->reference());
});

test('admins make a field global, required and limited to some ticket types', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.ticket-fields.store'), [
        'label' => 'Affected systems',
        'type' => 'multiselect',
        'options' => [],
    ])->assertSessionHasErrors('options');

    $this->actingAs($admin)->post(route('admin.ticket-fields.store'), [
        'label' => 'Affected systems',
        'type' => 'multiselect',
        'options' => ['Web', 'API'],
        'is_global' => true,
        'is_required' => true,
        'ticket_types' => ['incident', 'problem'],
    ])->assertSessionHasNoErrors();

    $field = TicketField::query()->where('key', 'affected_systems')->sole();
    expect($field->is_global)->toBeTrue()
        ->and($field->is_required)->toBeTrue()
        ->and($field->ticket_types)->toBe(['incident', 'problem']);

    $this->actingAs($admin)
        ->put(route('admin.ticket-fields.update', $field), ['label' => 'Affected systems', 'options' => ['Web', 'API'], 'ticket_types' => ['meeting']])
        ->assertSessionHasErrors('ticket_types.0');
});
