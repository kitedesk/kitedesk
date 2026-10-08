<?php

use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Notifications\TicketMentioned;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->agent = User::factory()->agent()->create();
    $this->colleague = User::factory()->lightAgent()->create(['name' => 'Jane Doe']);
    $this->requester = User::factory()->create();
    $this->ticket = Ticket::factory()->for($this->requester, 'requester')->create();
    $this->other = Ticket::factory()->create();
});

function mention(User $user): string
{
    return '<span data-type="mention" data-id="'.$user->id.'" data-label="'.$user->name.'">@'.$user->name.'</span>';
}

function ticketReference(Ticket $ticket): string
{
    return '<a data-type="ticket" data-id="'.$ticket->id.'" href="/agent/tickets/'.$ticket->id.'">'.$ticket->reference().'</a>';
}

test('agents mentioned in an internal note are notified once', function () {
    Notification::fake();

    $this->actingAs($this->agent)
        ->post(route('agent.tickets.messages.store', $this->ticket), [
            'body' => '<p>'.mention($this->colleague).' and again '.mention($this->colleague).', see '.ticketReference($this->other).'</p>',
            'is_internal' => true,
        ])
        ->assertSessionHasNoErrors();

    $body = $this->ticket->messages()->sole()->body;
    expect($body)->toContain('data-type="mention" data-id="'.$this->colleague->id.'"')
        ->toContain('data-type="ticket" data-id="'.$this->other->id.'"');

    Notification::assertSentToTimes($this->colleague, TicketMentioned::class, 1);
    Notification::assertSentTo($this->colleague, TicketMentioned::class, function (TicketMentioned $notification, array $channels) {
        $data = $notification->toArray($this->colleague);

        return $channels === ['mail', 'database']
            && $data['kind'] === 'ticket_mentioned'
            && $data['author'] === $this->agent->name
            && str_contains($data['excerpt'], '@Jane Doe');
    });
});

test('authors, customers and unknown ids are never notified', function () {
    Notification::fake();

    app(AddMessage::class)->handle(
        $this->ticket,
        $this->agent,
        '<p>'.mention($this->agent).mention($this->requester).'<span data-type="mention" data-id="999999">@Ghost</span></p>',
        isInternal: true,
    );

    Notification::assertNotSentTo([$this->agent, $this->requester], TicketMentioned::class);
    Notification::assertCount(0);
});

test('public replies and customer messages keep mentions and ticket links as plain text', function () {
    Notification::fake();

    app(AddMessage::class)->handle($this->ticket, $this->agent, '<p>'.mention($this->colleague).' '.ticketReference($this->other).'</p>');
    app(AddMessage::class)->handle($this->ticket, $this->requester, '<p>'.mention($this->colleague).'</p>', isInternal: true);

    [$reply, $customerMessage] = $this->ticket->messages()->oldest('id')->pluck('body')->all();

    expect($reply)->toBe('<p>@Jane Doe '.e($this->other->reference()).'</p>')
        ->and($customerMessage)->toBe('<p>@Jane Doe</p>');
    Notification::assertNotSentTo($this->colleague, TicketMentioned::class);
});

test('the mention picker searches staff only, from the first letter', function () {
    User::factory()->create(['name' => 'Jack Customer']);
    $this->agent->update(['name' => 'Agent Smith', 'email' => 'smith@example.com']);
    $this->colleague->update(['email' => 'jane@example.com']);

    $this->actingAs($this->agent)
        ->getJson(route('agent.search', ['q' => 'J', 'staff_only' => 1]))
        ->assertOk()
        ->assertJsonPath('tickets', [])
        ->assertJsonCount(1, 'users')
        ->assertJsonPath('users.0.id', $this->colleague->id);

    $this->actingAs($this->requester)
        ->getJson(route('agent.search', ['q' => 'J', 'staff_only' => 1]))
        ->assertForbidden();
});
