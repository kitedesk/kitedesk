<?php

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Mail\Enums\EmailTemplateEvent;
use App\Domain\Secrets\Models\Secret;
use App\Domain\Secrets\Notifications\SecretSubmitted;
use App\Domain\Tickets\Models\Ticket;
use App\Mail\TicketEmail;
use App\Models\User;
use Database\Factories\SecretFactory;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->agent = User::factory()->agent()->create();
    $this->ticket = Ticket::factory()->create();
    $this->customer = $this->ticket->requester;
});

test('an agent requests a secret and sends it with a reply', function () {
    $this->actingAs($this->agent)
        ->postJson(route('agent.tickets.secrets.store', $this->ticket), [
            'kind' => 'request',
            'label' => 'VPN password',
            'max_views' => 3,
            'expires_in_hours' => 24,
        ])
        ->assertCreated()
        ->assertJsonPath('secret.label', 'VPN password')
        ->assertJsonPath('secret.status', 'pending');

    $secret = Secret::sole();

    $this->actingAs($this->agent)
        ->post(route('agent.tickets.messages.store', $this->ticket), [
            'body' => '<p>Please send it here.</p>',
            'secrets' => [$secret->token],
        ])
        ->assertSessionHasNoErrors();

    $message = $this->ticket->messages()->sole();
    expect($secret->fresh()->ticket_message_id)->toBe($message->id);

    $mail = (new TicketEmail($this->ticket, EmailTemplateEvent::AgentReply, $this->customer, $message))->render();
    expect($mail)->toContain(route('secrets.show', $secret))->toContain('Provide securely');

    $this->actingAs($this->customer)
        ->get(route('portal.tickets.show', $this->ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('messages.0.secrets.0.url', route('secrets.show', $secret))
            ->missing('messages.0.secrets.0.can_reveal'));
});

test('requesting a secret needs the permission', function () {
    $withoutPermission = User::factory()->withPermissions([Permission::ReplyToTickets])->create();

    $this->actingAs($withoutPermission)
        ->postJson(route('agent.tickets.secrets.store', $this->ticket), ['kind' => 'request', 'label' => 'Password', 'max_views' => 1, 'expires_in_hours' => 1])
        ->assertForbidden();

    $this->actingAs($this->customer)
        ->postJson(route('agent.tickets.secrets.store', $this->ticket), ['kind' => 'request', 'label' => 'Password', 'max_views' => 1, 'expires_in_hours' => 1])
        ->assertForbidden();
});

test('the requester answers once while signed in, stored encrypted, and the agent is notified', function () {
    Notification::fake();
    $secret = Secret::factory()->request()->for($this->ticket)->create(['created_by' => $this->agent->id]);

    $this->get(route('secrets.show', $secret))->assertRedirect(route('login'));
    $this->postJson(route('secrets.submit', $secret), ['secret' => 'hunter2'])->assertUnauthorized();

    $this->actingAs($this->customer)
        ->get(route('secrets.show', $secret))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertInertia(fn (Assert $page) => $page->component('guest/secret')->where('secret.status', 'pending'));

    $this->actingAs($this->customer)
        ->postJson(route('secrets.submit', $secret), ['secret' => 'hunter2'])
        ->assertOk()
        ->assertJsonPath('status', 'available');

    $stored = $secret->fresh();
    expect($stored->ciphertext)->not->toBeNull()->not->toContain('hunter2')
        ->and($stored->submitted_by)->toBe($this->customer->id);
    Notification::assertSentTo($this->agent, SecretSubmitted::class);

    $this->actingAs($this->customer)
        ->postJson(route('secrets.submit', $secret), ['secret' => 'again'])
        ->assertStatus(410);
});

test('people copied may answer, but other customers may not', function () {
    $secret = Secret::factory()->request()->for($this->ticket)->create();
    $copied = User::factory()->create();
    $this->ticket->collaborators()->attach($copied);

    $this->actingAs(User::factory()->create())->get(route('secrets.show', $secret))->assertForbidden();
    $this->actingAs(User::factory()->create())->postJson(route('secrets.submit', $secret), ['secret' => 'x'])->assertForbidden();

    $this->actingAs($copied)->postJson(route('secrets.submit', $secret), ['secret' => 'from a colleague'])->assertOk();
});

test('agents who see the ticket reveal the answer until the views run out', function () {
    $secret = Secret::factory()->submitted()->for($this->ticket)->create(['max_views' => 2]);
    $anotherAgent = User::factory()->agent()->create();

    $this->actingAs(User::factory()->lightAgent()->create())
        ->postJson(route('agent.secrets.reveal', $secret))
        ->assertForbidden();
    $this->actingAs($this->customer)
        ->postJson(route('agent.secrets.reveal', $secret))
        ->assertForbidden();

    $this->actingAs($this->agent)
        ->postJson(route('agent.secrets.reveal', $secret))
        ->assertOk()
        ->assertJson(['secret' => SecretFactory::CONTENT, 'views_left' => 1]);
    $this->actingAs($anotherAgent)->postJson(route('agent.secrets.reveal', $secret))->assertOk();

    expect($secret->fresh())->ciphertext->toBeNull()->destroyed_at->not->toBeNull();
    $this->actingAs($this->agent)->postJson(route('agent.secrets.reveal', $secret))->assertStatus(410);
});

test('secrets can only go out with public replies, and only the agent\'s own unsent ones', function () {
    $secret = Secret::factory()->request()->for($this->ticket)->create(['created_by' => $this->agent->id]);
    $someoneElses = Secret::factory()->for($this->ticket)->create();

    $this->actingAs($this->agent)
        ->post(route('agent.tickets.messages.store', $this->ticket), ['body' => '<p>Note</p>', 'is_internal' => true, 'secrets' => [$secret->token]])
        ->assertSessionHasErrors('secrets');

    $this->actingAs($this->agent)
        ->post(route('agent.tickets.messages.store', $this->ticket), ['body' => '<p>Hi</p>', 'secrets' => [$someoneElses->token]])
        ->assertSessionHasErrors('secrets.0');
});

test('revoking wipes the content and expired secrets are purged', function () {
    $revoked = Secret::factory()->for($this->ticket)->create(['created_by' => $this->agent->id]);
    $expired = Secret::factory()->for($this->ticket)->create(['expires_at' => now()->subMinute()]);
    $current = Secret::factory()->for($this->ticket)->create();

    $this->actingAs($this->agent)->delete(route('agent.secrets.destroy', $revoked))->assertRedirect();
    $this->artisan('secrets:purge')->assertSuccessful();

    expect($revoked->fresh())->ciphertext->toBeNull()->status()->value->toBe('revoked')
        ->and($expired->fresh())->ciphertext->toBeNull()->status()->value->toBe('expired')
        ->and($current->fresh()->ciphertext)->not->toBeNull()
        ->and(Activity::query()->forSubject($this->ticket)->pluck('event')->all())->toContain('secret_revoked', 'secret_expired');
});
