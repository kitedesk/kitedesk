<?php

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Accounts\Models\Group;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->group = Group::factory()->create();
    $this->inGroup = Ticket::factory()->create(['group_id' => $this->group->id, 'subject' => 'Group ticket']);
    $this->elsewhere = Ticket::factory()->create(['group_id' => Group::factory(), 'subject' => 'Other ticket']);
});

test('a role limited to its groups only sees tickets in those groups or assigned to the member', function () {
    $agent = User::factory()->withPermissions([Permission::UpdateTickets], TicketAccess::Groups)->create();
    $agent->groups()->attach($this->group);
    $assigned = Ticket::factory()->assignedTo($agent)->create(['group_id' => null, 'subject' => 'Assigned ticket']);

    $this->actingAs($agent)
        ->get(route('agent.tickets.index', ['view' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets.data', 2)
            ->where('agentNav.views', fn ($views) => collect($views)->firstWhere('key', 'all')['count'] === 2));

    $this->actingAs($agent)->get(route('agent.tickets.show', $this->inGroup))->assertOk();
    $this->actingAs($agent)->get(route('agent.tickets.show', $assigned))->assertOk();
    $this->actingAs($agent)->get(route('agent.tickets.show', $this->elsewhere))->assertForbidden();
    $this->actingAs($agent)->patch(route('agent.tickets.update', $this->elsewhere), ['priority' => 'high'])->assertForbidden();

    $this->actingAs($agent)
        ->getJson(route('agent.search', ['q' => 'ticket']))
        ->assertJsonCount(2, 'tickets');
});

test('a role limited to assigned tickets sees nothing else, also through the API', function () {
    $agent = User::factory()->withPermissions([], TicketAccess::Assigned)->create();
    $agent->groups()->attach($this->group);
    $assigned = Ticket::factory()->assignedTo($agent)->create();

    Sanctum::actingAs($agent, ['tickets:read']);

    $this->getJson(route('api.v1.tickets.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $assigned->id);
    $this->getJson(route('api.v1.tickets.show', $this->inGroup))->assertForbidden();
});

test('without the reply permission staff can only add internal notes', function () {
    $agent = User::factory()->withPermissions([Permission::UpdateTickets])->create();

    $this->actingAs($agent)
        ->post(route('agent.tickets.messages.store', $this->inGroup), ['body' => '<p>Hi</p>', 'is_internal' => false])
        ->assertForbidden();
    $this->actingAs($agent)
        ->post(route('agent.tickets.messages.store', $this->inGroup), ['body' => '<p>Note</p>', 'is_internal' => true])
        ->assertSessionHasNoErrors();

    expect($this->inGroup->messages()->sole()->is_internal)->toBeTrue();
});

test('forwarding needs its own permission and a target who can be assigned tickets', function () {
    $agent = User::factory()->withPermissions([Permission::UpdateTickets])->create();
    $colleague = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->post(route('agent.tickets.forward', $this->inGroup), ['assignee_id' => $colleague->id])
        ->assertForbidden();

    $forwarder = User::factory()->withPermissions([Permission::UpdateTickets, Permission::ForwardTickets])->create();
    $notAssignable = User::factory()->withPermissions([Permission::UpdateTickets])->create();

    $this->actingAs($forwarder)
        ->post(route('agent.tickets.forward', $this->inGroup), ['assignee_id' => $notAssignable->id])
        ->assertSessionHasErrors('assignee_id');
    $this->actingAs($forwarder)
        ->patch(route('agent.tickets.update', $this->inGroup), ['assignee_id' => $notAssignable->id])
        ->assertSessionHasErrors('assignee_id');
});

test('deleting a ticket needs the permission and removes it with its messages', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->delete(route('agent.tickets.destroy', $this->inGroup))
        ->assertForbidden();

    $this->inGroup->messages()->create(['body' => '<p>Hello</p>', 'author_id' => $this->inGroup->requester_id, 'channel' => 'portal']);

    $this->actingAs(User::factory()->withPermissions([Permission::DeleteTickets])->create())
        ->delete(route('agent.tickets.destroy', $this->inGroup))
        ->assertRedirect(route('agent.tickets.index'));

    expect(Ticket::query()->whereKey($this->inGroup->id)->exists())->toBeFalse()
        ->and($this->elsewhere->fresh())->not->toBeNull();
});

test('exporting reports needs its own permission', function () {
    $viewer = User::factory()->withPermissions([Permission::ViewReports])->create();

    $this->actingAs($viewer)->get(route('agent.reports.index'))->assertOk();
    $this->actingAs($viewer)->get(route('agent.reports.export'))->assertForbidden();

    $this->actingAs(User::factory()->agent()->create())->get(route('agent.reports.export'))->assertOk();
});

test('the shared auth props list what the role allows', function () {
    $this->actingAs(User::factory()->withPermissions([Permission::ViewReports], TicketAccess::Groups)->create())
        ->get(route('agent.tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can', fn ($can) => $can['reports.view'] === true && $can['tickets.reply'] === false)
            ->where('auth.ticketAccess', 'groups'));
});
