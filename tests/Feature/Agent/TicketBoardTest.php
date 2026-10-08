<?php

use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\CustomStatus;
use App\Domain\Tickets\Models\SavedView;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\BoardPreferences;
use App\Domain\Tickets\Support\CustomStatuses;
use App\Domain\Tickets\Support\TicketBoard;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->agent = User::factory()->agent()->create();
    $this->vendor = CustomStatus::factory()->category(TicketStatus::Pending)->create(['name' => 'Waiting on vendor']);
});

/**
 * The board's visible lanes, keyed by lane key.
 *
 * @param  array<string, mixed>  $query
 * @return array<string, array<string, mixed>>
 */
function boardOf(User $agent, array $query = []): array
{
    $board = null;

    test()->actingAs($agent)
        ->get(route('agent.tickets.index', ['view' => 'all', 'layout' => 'board', ...$query]))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$board) {
            $board = $page->toArray()['props']['board'];

            return $page->where('layout', 'board')->where('tickets', null);
        });

    return collect($board['lanes'])->keyBy('key')->all();
}

test('the board has a lane per custom status with counts and cards', function () {
    $open = Ticket::factory()->count(2)->status(TicketStatus::Open)->create();
    Ticket::factory()->create(['ticket_status_id' => $this->vendor->id]);
    Ticket::factory()->status(TicketStatus::Solved)->create();

    $lanes = boardOf($this->agent);
    $openLane = $lanes[(string) CustomStatuses::defaultFor(TicketStatus::Open)->id];

    expect($openLane['count'])->toBe(2)
        ->and(collect($openLane['tickets'])->pluck('id')->all())->toEqualCanonicalizing($open->pluck('id')->all())
        ->and($lanes[(string) $this->vendor->id]['label'])->toBe('Waiting on vendor')
        ->and($lanes[(string) $this->vendor->id]['count'])->toBe(1)
        // The "all" view only has unresolved tickets.
        ->and($lanes[(string) CustomStatuses::defaultFor(TicketStatus::Solved)->id]['count'])->toBe(0);
});

test('the board follows the queue filters and what the agent may see', function () {
    $restricted = User::factory()->withPermissions([], TicketAccess::Assigned)->create();
    $mine = Ticket::factory()->status(TicketStatus::Open)->assignedTo($restricted)->create(['priority' => TicketPriority::High]);
    Ticket::factory()->status(TicketStatus::Open)->create(['priority' => TicketPriority::High]);
    Ticket::factory()->status(TicketStatus::Open)->assignedTo($restricted)->create(['priority' => TicketPriority::Low]);

    $lanes = boardOf($restricted, ['filter' => ['priority' => 'high']]);

    expect(collect($lanes)->sum('count'))->toBe(1)
        ->and($lanes[(string) CustomStatuses::defaultFor(TicketStatus::Open)->id]['tickets'][0]['id'])->toBe($mine->id);
});

test('agents hide and reorder lanes, and group by assignee', function () {
    $colleague = User::factory()->agent()->create(['name' => 'Ana']);
    Ticket::factory()->status(TicketStatus::Open)->assignedTo($colleague)->create();
    Ticket::factory()->status(TicketStatus::Open)->create();

    BoardPreferences::update($this->agent, [
        'group_by' => 'assignee',
        'columns' => ['assignee' => ['order' => [(string) $colleague->id], 'hidden' => [(string) $this->agent->id]]],
    ]);

    $lanes = boardOf($this->agent);

    expect((string) array_key_first($lanes))->toBe((string) $colleague->id)
        ->and($lanes)->toHaveKey(TicketBoard::NONE)
        ->and($lanes[TicketBoard::NONE]['count'])->toBe(1)
        ->and($lanes)->not->toHaveKey((string) $this->agent->id);
});

test('lanes show 50 cards and load the rest on demand', function () {
    Ticket::factory()->count(52)->status(TicketStatus::Open)->create();
    $lane = (string) CustomStatuses::defaultFor(TicketStatus::Open)->id;

    $lanes = boardOf($this->agent);
    expect($lanes[$lane]['tickets'])->toHaveCount(50)
        ->and($lanes[$lane]['has_more'])->toBeTrue();

    $this->actingAs($this->agent)
        ->getJson(route('agent.tickets.board.lane', ['view' => 'all', 'lane' => $lane, 'offset' => 50]))
        ->assertOk()
        ->assertJsonCount(2, 'tickets')
        ->assertJsonPath('has_more', false);

    $this->actingAs($this->agent)
        ->getJson(route('agent.tickets.board.lane', ['view' => 'all', 'lane' => 'nope']))
        ->assertNotFound();
});

test('the layout comes from the link, then the saved view, then the agent preference', function () {
    $layoutOf = fn (array $query): string => $this->actingAs($this->agent)
        ->get(route('agent.tickets.index', $query))
        ->viewData('page')['props']['layout'];

    expect($layoutOf([]))->toBe('list');

    BoardPreferences::update($this->agent, ['tickets_layout' => 'board']);
    expect($layoutOf([]))->toBe('board');

    $view = SavedView::factory()->for($this->agent, 'owner')->create(['layout' => 'list']);
    expect($layoutOf(['view' => $view->key()]))->toBe('list')
        ->and($layoutOf(['view' => $view->key(), 'layout' => 'board']))->toBe('board');
});

test('dropping a card on a lane changes the ticket, except closed tickets', function () {
    $ticket = Ticket::factory()->status(TicketStatus::Open)->create();

    $this->actingAs($this->agent)
        ->patch(route('agent.tickets.update', $ticket), ['ticket_status_id' => $this->vendor->id])
        ->assertSessionHasNoErrors();
    expect($ticket->refresh()->status)->toBe(TicketStatus::Pending);

    $closed = Ticket::factory()->status(TicketStatus::Closed)->create();
    $this->actingAs($this->agent)
        ->patch(route('agent.tickets.update', $closed), ['ticket_status_id' => $this->vendor->id])
        ->assertForbidden();
});

test('customers cannot use the board or its preferences', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)->get(route('agent.tickets.index', ['layout' => 'board']))->assertForbidden();
    $this->actingAs($customer)->getJson(route('agent.tickets.board.lane', ['lane' => '1']))->assertForbidden();
    $this->actingAs($customer)->patch(route('agent.preferences.board'), ['group_by' => 'priority'])->assertForbidden();
});

test('the queue always gets its filters as an object, even without any', function () {
    $response = $this->actingAs($this->agent)->get(route('agent.tickets.index', ['layout' => 'board']));

    expect(json_encode($response->viewData('page')['props']['filters']))->toBe('{"filter":{},"sort":null}');
});
