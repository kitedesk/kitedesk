<?php

use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\CustomStatus;
use App\Domain\Tickets\Models\SavedView;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\TicketViews;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->vendor = CustomStatus::factory()->category(TicketStatus::Pending)->create(['name' => 'Waiting on vendor']);
});

test('the sidebar lists active statuses with the tickets the agent can see', function () {
    $agent = User::factory()->withPermissions([], TicketAccess::Assigned)->create();
    Ticket::factory()->assignedTo($agent)->create(['ticket_status_id' => $this->vendor->id]);
    Ticket::factory()->create(['ticket_status_id' => $this->vendor->id]);
    $inactive = CustomStatus::factory()->inactive()->create();

    $statuses = collect(TicketViews::summary($agent))->filter(fn (array $view): bool => isset($view['status']))->keyBy('key');

    expect($statuses["status:{$this->vendor->id}"]['label'])->toBe('Waiting on vendor')
        ->and($statuses["status:{$this->vendor->id}"]['count'])->toBe(1)
        ->and($statuses)->not->toHaveKey("status:{$inactive->id}");
});

test('a status queue shows every ticket in that status, solved ones too', function () {
    $agent = User::factory()->agent()->create();
    $solvedStatus = CustomStatus::factory()->category(TicketStatus::Solved)->create();
    $inStatus = Ticket::factory()->status(TicketStatus::Solved)->create(['ticket_status_id' => $solvedStatus->id]);
    Ticket::factory()->status(TicketStatus::Solved)->create();

    $this->actingAs($agent)
        ->get(route('agent.tickets.index', ['view' => "status:{$solvedStatus->id}"]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('view', "status:{$solvedStatus->id}")
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $inStatus->id));

    expect(TicketViews::resolve('status:999999'))->toBe(TicketViews::DEFAULT);
});

test('views saved from a status queue keep it as their base', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)->post(route('agent.views.store'), [
        'name' => 'Vendor, urgent',
        'view' => "status:{$this->vendor->id}",
        'filter' => ['priority' => 'urgent'],
    ])->assertSessionHasNoErrors();

    expect(SavedView::query()->sole()->filters)->toBe(['view' => "status:{$this->vendor->id}", 'priority' => 'urgent']);
});
