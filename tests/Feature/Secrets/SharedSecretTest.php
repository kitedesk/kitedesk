<?php

use App\Domain\Secrets\Models\Secret;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an agent shares a secret, stored encrypted and never in the reply', function () {
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->create();

    $token = $this->actingAs($agent)
        ->postJson(route('agent.tickets.secrets.store', $ticket), [
            'kind' => 'share',
            'label' => 'Wi-Fi password',
            'max_views' => 2,
            'expires_in_hours' => 72,
            'secret' => 'correct horse battery staple',
        ])
        ->assertCreated()
        ->assertJsonMissingPath('secret.secret')
        ->json('secret.token');

    expect(Secret::sole()->ciphertext)->not->toContain('correct horse');

    $this->actingAs($agent)
        ->post(route('agent.tickets.messages.store', $ticket), ['body' => '<p>Here it is.</p>', 'secrets' => [$token]])
        ->assertSessionHasNoErrors();

    expect($ticket->messages()->sole()->body)->not->toContain('correct horse');
});

test('the signed-in requester reveals a share, which counts views until it is wiped', function () {
    $secret = Secret::factory()->create(['max_views' => 2]);
    $customer = $secret->ticket->requester;

    $this->postJson(route('secrets.reveal', $secret))->assertUnauthorized();

    $this->actingAs($customer)
        ->get(route('secrets.show', $secret))
        ->assertInertia(fn (Assert $page) => $page->where('secret.views_left', 2)->missing('secret.secret'));
    expect($secret->fresh()->views)->toBe(0);

    $this->actingAs($customer)
        ->postJson(route('secrets.reveal', $secret))
        ->assertOk()
        ->assertJson(['secret' => 'factory-secret', 'views_left' => 1]);
    $this->actingAs($customer)->postJson(route('secrets.reveal', $secret))->assertOk();

    expect($secret->fresh())->views->toBe(2)->ciphertext->toBeNull();
    $this->actingAs($customer)->postJson(route('secrets.reveal', $secret))->assertStatus(410);
});

test('other customers and agents cannot reveal a share', function () {
    $secret = Secret::factory()->create();

    $this->actingAs(User::factory()->create())->postJson(route('secrets.reveal', $secret))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->postJson(route('secrets.reveal', $secret))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('secrets.show', $secret))
        ->assertRedirect(route('agent.tickets.show', $secret->ticket));
});

test('expired and revoked shares can no longer be revealed', function () {
    $expired = Secret::factory()->create(['expires_at' => now()->subMinute()]);
    $revoked = Secret::factory()->create(['revoked_at' => now()]);

    $this->actingAs($expired->ticket->requester)->postJson(route('secrets.reveal', $expired))->assertStatus(410);
    $this->actingAs($revoked->ticket->requester)->postJson(route('secrets.reveal', $revoked))->assertStatus(410);
});
