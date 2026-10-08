<?php

use App\Domain\Mail\Enums\EmailTemplateEvent;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Domain\Tickets\Notifications\TicketAccessLink;
use App\Domain\Tickets\Notifications\TicketReceived;
use App\Domain\Tickets\Notifications\TicketReplied;
use App\Domain\Tickets\Support\GuestAccess;
use App\Mail\TicketEmail;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $data
 */
function submitGuestRequest(array $data = []): TestResponse
{
    return test()->post(route('guest.tickets.store'), [
        'name' => 'Jamie Guest',
        'email' => 'Jamie@Example.com',
        'subject' => 'Printer offline',
        'body' => '<p>It stopped working.</p>',
        ...$data,
    ]);
}

test('people without an account can submit a request and follow it right away', function () {
    Mail::fake();

    $response = submitGuestRequest();

    $ticket = Ticket::query()->sole();
    $response->assertRedirect(route('guest.tickets.show', $ticket));
    expect($ticket->requester->email)->toBe('jamie@example.com')
        ->and($ticket->requester->isCustomer())->toBeTrue();
    Mail::assertQueued(TicketEmail::class, fn (TicketEmail $mail): bool => $mail->event === EmailTemplateEvent::TicketReceived && $mail->hasTo('jamie@example.com'));

    $this->get(route('guest.tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page->component('guest/tickets/show')->where('ticket.id', $ticket->id)->where('guest.name', 'Jamie Guest'));

    $this->post(route('guest.tickets.replies.store', $ticket), ['body' => '<p>Still broken.</p>'])->assertSessionHasNoErrors();
    expect($ticket->messages()->count())->toBe(2)
        ->and($ticket->messages()->latest('id')->first()?->author_id)->toBe($ticket->requester_id);
});

test('using an existing account email sends a link instead of opening the ticket', function () {
    Mail::fake();
    $existing = User::factory()->create(['email' => 'jamie@example.com']);

    submitGuestRequest()->assertRedirect(route('guest.check'));

    $ticket = Ticket::query()->sole();
    expect($ticket->requester_id)->toBe($existing->id)
        ->and($ticket->tags->pluck('name')->all())->toBe(['unverified_sender']);
    Mail::assertQueued(TicketEmail::class, fn (TicketEmail $mail): bool => $mail->event === EmailTemplateEvent::TicketReceived && $mail->hasTo($existing->email));
    $this->get(route('guest.tickets.show', $ticket))->assertRedirect(route('guest.check'));
});

test('guests still get their link when the auto-reply is switched off', function () {
    Notification::fake();
    EmailTemplate::query()->create(['event' => EmailTemplateEvent::TicketReceived, 'subject' => 'x', 'body' => 'y', 'is_active' => false]);

    submitGuestRequest();

    Notification::assertSentTo(Ticket::query()->sole()->requester, TicketReceived::class);
});

test('a signed link opens the ticket and an expired or tampered one does not', function () {
    $requester = User::factory()->unverified()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();
    TicketMessage::factory()->for($ticket)->internal()->create(['body' => '<p>Secret note</p>']);

    $this->get(GuestAccess::linkFor($ticket, $requester))->assertRedirect(route('guest.tickets.show', $ticket));
    $this->get(route('guest.tickets.show', $ticket))
        ->assertOk()
        ->assertDontSee('Secret note');

    $this->flushSession();
    $other = Ticket::factory()->create();
    $this->get(route('guest.tickets.access', [$other, $requester]))->assertForbidden();
    $this->get(str_replace("/{$ticket->id}/", "/{$other->id}/", GuestAccess::linkFor($ticket, $requester)))->assertForbidden();

    $expired = URL::temporarySignedRoute('guest.tickets.access', now()->subMinute(), ['ticket' => $ticket->id, 'user' => $requester->id]);
    $this->get($expired)->assertForbidden();
});

test('guests cannot reply without access or to a closed request', function () {
    $requester = User::factory()->unverified()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->post(route('guest.tickets.replies.store', $ticket), ['body' => '<p>Hi</p>'])->assertForbidden();

    $this->get(GuestAccess::linkFor($ticket, $requester));
    $ticket->update(['status' => TicketStatus::Closed]);
    $this->post(route('guest.tickets.replies.store', $ticket), ['body' => '<p>Hi</p>'])->assertForbidden();
});

test('checking a request emails a link without revealing whether it exists', function () {
    Notification::fake();
    $requester = User::factory()->create(['email' => 'pat@example.com']);
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->post(route('guest.check.store'), ['email' => 'PAT@example.com', 'ticket' => "#{$ticket->id}"])->assertSessionHasNoErrors();
    $this->post(route('guest.check.store'), ['email' => 'someone@example.com', 'ticket' => $ticket->id])->assertSessionHasNoErrors();
    $this->post(route('guest.check.store'), ['email' => 'pat@example.com', 'ticket' => 999999])->assertSessionHasNoErrors();

    Notification::assertSentToTimes($requester, TicketAccessLink::class, 1);
    Notification::assertCount(1);
});

test('requests can be checked by their formatted number', function () {
    Notification::fake();
    $requester = User::factory()->create(['email' => 'pat@example.com']);
    Ticket::factory()->for($requester, 'requester')->create(['number' => 'TKT-00042']);

    $this->post(route('guest.check.store'), ['email' => 'pat@example.com', 'ticket' => 'TKT-00042'])->assertSessionHasNoErrors();

    Notification::assertSentTo($requester, TicketAccessLink::class);
});

test('customers who cannot sign in get magic links in reply emails', function () {
    $agent = User::factory()->agent()->create();
    $guest = User::factory()->unverified()->create();
    $member = User::factory()->create();
    $ticket = Ticket::factory()->for($guest, 'requester')->create();
    $message = TicketMessage::factory()->for($ticket)->create(['author_id' => $agent->id, 'is_internal' => false]);

    $ticket->collaborators()->attach($member);

    expect((new TicketReplied($message))->toMail($guest)->render())->toContain('/requests/'.$ticket->id.'/access/')
        ->and((new TicketReplied($message))->toMail($member)->render())->toContain(route('portal.tickets.show', $ticket));
});

test('the public form can be turned off', function () {
    config(['kitedesk.guest_tickets' => false]);

    $this->get(route('guest.tickets.create'))->assertNotFound();
    submitGuestRequest()->assertNotFound();
});

test('signed-in customers are sent to the portal form', function () {
    $this->actingAs(User::factory()->create())->get(route('guest.tickets.create'))->assertRedirect(route('portal.tickets.create'));
});

test('a captcha is required when Turnstile is configured', function () {
    config(['services.turnstile.site_key' => 'site', 'services.turnstile.secret_key' => 'secret']);
    Http::fake(['challenges.cloudflare.com/*' => Http::sequence()->push(['success' => false])->push(['success' => true])]);

    submitGuestRequest()->assertSessionHasErrors('cf-turnstile-response');
    submitGuestRequest(['cf-turnstile-response' => 'bad-token'])->assertSessionHasErrors('cf-turnstile-response');
    submitGuestRequest(['cf-turnstile-response' => 'good-token'])->assertSessionHasNoErrors();

    Http::assertSent(fn ($request) => $request['secret'] === 'secret' && $request['response'] === 'good-token');
    $this->get(route('guest.tickets.create'))->assertInertia(fn (Assert $page) => $page->where('captchaSiteKey', 'site'));
});

test('registration checks the captcha when Turnstile is configured', function () {
    config(['services.turnstile.site_key' => 'site', 'services.turnstile.secret_key' => 'secret']);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

    $this->post(route('register.store'), [
        'name' => 'Robot',
        'email' => 'robot@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'cf-turnstile-response' => 'nope',
    ])->assertSessionHasErrors('cf-turnstile-response');

    expect(User::query()->where('email', 'robot@example.com')->exists())->toBeFalse();
});
