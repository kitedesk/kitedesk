<?php

use App\Domain\Mail\Actions\ReceiveEmail;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Mail\Support\InboundEmail;
use App\Domain\Tickets\Actions\CreateTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketNumberReset;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\TicketNumberFormat;
use App\Domain\Tickets\Support\TicketNumberGenerator;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function numberedTicket(?User $requester = null): Ticket
{
    return app(CreateTicket::class)->handle(
        $requester ?? User::factory()->create(),
        ['subject' => 'Help', 'body' => '<p>Hi</p>'],
        TicketChannel::Portal,
    );
}

test('tickets are numbered from the sequence by default', function () {
    app(TicketNumberGenerator::class)->setNext(7);

    $first = numberedTicket();
    $second = numberedTicket();

    expect($first->number)->toBe('7')
        ->and($first->reference())->toBe('#7')
        ->and($second->number)->toBe('8');
});

test('a custom format pads the sequence and can restart it every year', function () {
    $this->travelTo('2026-12-31 23:00:00');
    (new TicketNumberFormat('{yyyy}-{seq:4}', TicketNumberReset::Yearly))->save();
    app(TicketNumberGenerator::class)->setNext(41);

    expect(numberedTicket()->reference())->toBe('2026-0041')
        ->and(numberedTicket()->reference())->toBe('2026-0042');

    $this->travelTo('2027-01-01 08:00:00');

    expect(numberedTicket()->reference())->toBe('2027-0001');
});

test('random numbers have the requested length and taken numbers are skipped', function () {
    (new TicketNumberFormat('{random:8}'))->save();
    expect(numberedTicket()->number)->toMatch('/^[1-9]\d{7}$/');

    (new TicketNumberFormat('TKT-{seq}'))->save();
    Ticket::factory()->create(['number' => 'TKT-5']);
    app(TicketNumberGenerator::class)->setNext(5);

    expect(numberedTicket()->number)->toBe('TKT-6');
});

test('admins change the format and the next number, and bad formats are rejected', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.ticket-numbers.edit'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/ticket-numbers/edit')->where('settings.format', '{seq}'));

    $this->actingAs($admin)
        ->put(route('admin.ticket-numbers.update'), ['format' => 'TKT-{seq:5}', 'reset' => 'never', 'next' => 42])
        ->assertSessionHasNoErrors();

    expect(numberedTicket()->reference())->toBe('TKT-00042');

    foreach (['TKT-{yyyy}', '{random:4}', 'TKT {seq}', '{seq}<script>'] as $format) {
        $this->actingAs($admin)
            ->put(route('admin.ticket-numbers.update'), ['format' => $format, 'reset' => 'never', 'next' => 1])
            ->assertSessionHasErrors('format');
    }

    $this->actingAs(User::factory()->agent()->create())->get(route('admin.ticket-numbers.edit'))->assertForbidden();
});

test('agents find tickets by number, and email replies thread on it', function () {
    (new TicketNumberFormat('TKT-{seq:5}'))->save();
    app(TicketNumberGenerator::class)->setNext(42);
    $requester = User::factory()->create(['email' => 'casey@example.com']);
    $ticket = numberedTicket($requester);

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('agent.tickets.index', ['view' => 'all', 'filter' => ['search' => 'TKT-00042']]))
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.number', 'TKT-00042'));

    $message = app(ReceiveEmail::class)->handle(Mailbox::factory()->create(), new InboundEmail(
        fromEmail: 'casey@example.com',
        fromName: 'Casey',
        to: ['support@example.com'],
        cc: [],
        subject: 'Re: [TKT-00042] Help',
        text: 'Any news?',
        html: '',
        messageId: 'news@customer.test',
        inReplyTo: null,
        references: [],
        headers: [],
        attachments: [],
    ));

    expect($message?->ticket_id)->toBe($ticket->id);
});
