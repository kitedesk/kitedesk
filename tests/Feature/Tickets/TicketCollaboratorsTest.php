<?php

use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Notifications\TicketReplied;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->agent = User::factory()->agent()->create();
    $this->requester = User::factory()->create();
    $this->ticket = Ticket::factory()->for($this->requester, 'requester')->assignedTo($this->agent)->status(TicketStatus::Pending)->create();
});

test('agents copy existing people or new email addresses on a ticket', function () {
    $colleague = User::factory()->create();

    $this->actingAs($this->agent)
        ->post(route('agent.tickets.collaborators.store', $this->ticket), ['user_id' => $colleague->id])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->agent)
        ->post(route('agent.tickets.collaborators.store', $this->ticket), ['email' => 'New.Person@Example.com'])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->agent)
        ->post(route('agent.tickets.collaborators.store', $this->ticket), ['user_id' => $this->requester->id]);

    $newPerson = User::query()->where('email', 'new.person@example.com')->sole();
    expect($newPerson->isCustomer())->toBeTrue()
        ->and($this->ticket->collaborators()->pluck('users.id')->sort()->values()->all())->toBe([$colleague->id, $newPerson->id]);

    $this->actingAs($this->agent)->delete(route('agent.tickets.collaborators.destroy', [$this->ticket, $colleague]));
    expect($this->ticket->collaborators()->pluck('users.id')->all())->toBe([$newPerson->id]);
});

test('collaborators can see, list and reply to the ticket in the portal', function () {
    $colleague = User::factory()->create();
    $this->ticket->collaborators()->attach($colleague);

    $this->actingAs($colleague)->get(route('portal.tickets.show', $this->ticket))->assertOk();
    $this->actingAs($colleague)
        ->get(route('portal.tickets.index', ['status' => 'shared']))
        ->assertInertia(fn (Assert $page) => $page->where('status', 'shared')->where('tickets.data.0.id', $this->ticket->id));
    $this->actingAs($colleague)
        ->post(route('portal.tickets.replies.store', $this->ticket), ['body' => '<p>Same here.</p>'])
        ->assertSessionHasNoErrors();

    expect($this->ticket->refresh()->status)->toBe(TicketStatus::Open);

    $this->actingAs(User::factory()->create())->get(route('portal.tickets.show', $this->ticket))->assertForbidden();
});

test('public replies reach the requester and everyone copied, but not the author', function () {
    Notification::fake();
    $colleague = User::factory()->create();
    $this->ticket->collaborators()->attach($colleague);

    app(AddMessage::class)->handle($this->ticket, $this->agent, '<p>Fixed!</p>');
    Notification::assertSentTo([$this->requester, $colleague], TicketReplied::class);
    Notification::assertNotSentTo($this->agent, TicketReplied::class);

    Notification::fake();
    app(AddMessage::class)->handle($this->ticket, $colleague, '<p>Thanks</p>');
    Notification::assertSentTo([$this->requester, $this->agent], TicketReplied::class);
    Notification::assertNotSentTo($colleague, TicketReplied::class);

    Notification::fake();
    app(AddMessage::class)->handle($this->ticket, $this->agent, '<p>Note</p>', isInternal: true);
    Notification::assertNothingSent();
});
