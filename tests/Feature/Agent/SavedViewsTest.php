<?php

use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\SavedView;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\TicketViews;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->agent = User::factory()->agent()->create();
    $this->colleague = User::factory()->agent()->create();
});

test('agents save the current queue filters as a personal view', function () {
    $this->actingAs($this->agent)->post(route('agent.views.store'), [
        'name' => 'My urgent tickets',
        'view' => 'all',
        'filter' => ['priority' => 'urgent', 'assignee_id' => (string) $this->agent->id, 'search' => ''],
        'sort' => '-created_at',
    ])->assertSessionHasNoErrors();

    $view = SavedView::query()->sole();
    expect($view->user_id)->toBe($this->agent->id)
        ->and($view->is_shared)->toBeFalse()
        ->and($view->filters)->toBe(['view' => 'all', 'priority' => 'urgent', 'assignee_id' => 'me'])
        ->and($view->sort)->toBe('-created_at');

    $mine = Ticket::factory()->assignedTo($this->agent)->create(['priority' => TicketPriority::Urgent]);
    Ticket::factory()->assignedTo($this->colleague)->create(['priority' => TicketPriority::Urgent]);
    Ticket::factory()->assignedTo($this->agent)->create(['priority' => TicketPriority::Low]);

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.index', ['view' => $view->key()]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('view', $view->key())
            ->where('savedView.name', 'My urgent tickets')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $mine->id));
});

test('saving from a saved view keeps its base view and filters', function () {
    $base = SavedView::factory()->for($this->agent, 'owner')->create(['filters' => ['view' => 'unassigned', 'priority' => 'high']]);

    $this->actingAs($this->agent)->post(route('agent.views.store'), [
        'name' => 'High and open',
        'view' => $base->key(),
        'filter' => ['status' => 'open'],
    ])->assertSessionHasNoErrors();

    expect(SavedView::query()->latest('id')->firstOrFail()->filters)
        ->toBe(['view' => 'unassigned', 'priority' => 'high', 'status' => 'open']);
});

test('personal views stay private', function () {
    $view = SavedView::factory()->for($this->agent, 'owner')->create();

    expect(collect(TicketViews::summary($this->colleague))->pluck('key'))->not->toContain($view->key())
        ->and(TicketViews::resolve($view->key(), $this->colleague))->toBe(TicketViews::DEFAULT);

    $this->actingAs($this->colleague)->patch(route('agent.views.update', $view), ['name' => 'Mine now'])->assertForbidden();
    $this->actingAs($this->colleague)->delete(route('agent.views.destroy', $view))->assertForbidden();
});

test('only admins share views, and shared views follow whoever opens them', function () {
    $this->actingAs($this->agent)
        ->post(route('agent.views.store'), ['name' => 'Team', 'view' => 'all', 'is_shared' => true])
        ->assertSessionHasErrors('is_shared');

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)
        ->post(route('agent.views.store'), ['name' => 'My open tickets', 'view' => 'all', 'is_shared' => true, 'filter' => ['assignee_id' => (string) $admin->id]])
        ->assertSessionHasNoErrors();
    $shared = SavedView::query()->where('is_shared', true)->sole();

    $colleagueTicket = Ticket::factory()->assignedTo($this->colleague)->status(TicketStatus::Open)->create();
    Ticket::factory()->assignedTo($admin)->create();

    $this->actingAs($this->colleague)
        ->get(route('agent.tickets.index', ['view' => $shared->key()]))
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.id', $colleagueTicket->id));

    $this->actingAs($this->colleague)->patch(route('agent.views.update', $shared), ['name' => 'Renamed'])->assertForbidden();
});

test('owners rename, reorder and delete their views', function () {
    $first = SavedView::factory()->for($this->agent, 'owner')->create(['position' => 0]);
    $second = SavedView::factory()->for($this->agent, 'owner')->create(['position' => 1]);

    $this->actingAs($this->agent)->patch(route('agent.views.update', $first), ['name' => 'Renamed'])->assertRedirect();
    $this->actingAs($this->agent)->post(route('agent.views.move', $second), ['direction' => 'up']);

    expect($first->refresh()->name)->toBe('Renamed')
        ->and(SavedView::query()->ordered()->pluck('id')->all())->toBe([$second->id, $first->id]);

    $this->actingAs($this->agent)->delete(route('agent.views.destroy', $first));
    expect(SavedView::query()->pluck('id')->all())->toBe([$second->id]);
});

test('saved views remember a layout', function () {
    $this->actingAs($this->agent)->post(route('agent.views.store'), [
        'name' => 'Board',
        'view' => 'all',
        'layout' => 'board',
    ])->assertSessionHasNoErrors();

    $view = SavedView::query()->sole();
    expect($view->layout)->toBe('board');

    $this->actingAs($this->agent)->patch(route('agent.views.update', $view), ['layout' => null])->assertSessionHasNoErrors();
    expect($view->refresh()->layout)->toBeNull();

    $this->actingAs($this->agent)->patch(route('agent.views.update', $view), ['layout' => 'grid'])->assertSessionHasErrors('layout');
});
