<?php

use App\Domain\Accounts\Models\Organization;
use App\Domain\Tickets\Actions\DeleteTicket;
use App\Domain\Tickets\Actions\MergeTickets;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Webhooks\Jobs\DeliverWebhook;
use App\Domain\Webhooks\Models\Webhook;
use App\Domain\Webhooks\Models\WebhookDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    Sanctum::actingAs($this->admin, ['webhooks:manage', 'users:write']);
});

test('webhooks can be managed and their secret is shown once', function () {
    $response = $this->postJson(route('api.v1.webhooks.store'), ['name' => 'CRM', 'url' => 'https://crm.test/hook', 'events' => ['ticket.created', 'user.created'], 'is_active' => true])
        ->assertCreated()
        ->assertJsonPath('data.events', ['ticket.created', 'user.created']);

    $webhook = Webhook::query()->sole();
    expect($response->json('secret'))->toBe($webhook->secret);

    $this->getJson(route('api.v1.webhooks.show', $webhook))->assertOk()->assertJsonMissingPath('secret')->assertJsonMissingPath('data.secret');
    $this->putJson(route('api.v1.webhooks.update', $webhook), ['name' => 'CRM', 'url' => 'https://crm.test/hook', 'events' => ['ticket.updated']])
        ->assertOk()
        ->assertJsonPath('data.events', ['ticket.updated']);

    $secret = $this->postJson(route('api.v1.webhooks.rotate-secret', $webhook))->assertOk()->json('secret');
    expect($webhook->refresh()->secret)->toBe($secret);

    $this->deleteJson(route('api.v1.webhooks.destroy', $webhook))->assertNoContent();
});

test('webhooks must point to the public internet', function () {
    $this->postJson(route('api.v1.webhooks.store'), ['name' => 'Local', 'url' => 'http://127.0.0.1/hook', 'events' => ['ticket.created']])
        ->assertJsonValidationErrors('url');
});

test('deliveries can be listed and sent again with a new id', function () {
    Queue::fake();
    $webhook = Webhook::factory()->create(['events' => ['organization.created', 'organization.updated']]);

    $organization = $this->postJson(route('api.v1.organizations.store'), ['name' => 'Acme'])->json('data.id');
    $this->putJson(route('api.v1.organizations.update', $organization), ['name' => 'Acme Inc']);
    $this->putJson(route('api.v1.organizations.update', $organization), ['name' => 'Acme Inc']);

    $deliveries = $this->getJson(route('api.v1.webhooks.deliveries.index', $webhook))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.event', 'organization.updated')
        ->assertJsonPath('data.0.payload.changes.name', ['from' => 'Acme', 'to' => 'Acme Inc'])
        ->json('data');

    $copy = $this->postJson(route('api.v1.webhooks.deliveries.redeliver', [$webhook, $deliveries[1]['id']]))
        ->assertAccepted()
        ->json('data');

    expect($copy['uuid'])->not->toBe($deliveries[1]['uuid'])
        ->and($copy['payload']['id'])->toBe($copy['uuid'])
        ->and($copy['payload']['data'])->toBe($deliveries[1]['payload']['data']);

    Queue::assertPushed(DeliverWebhook::class, 3);
    expect(Organization::query()->sole()->name)->toBe('Acme Inc');
});

test('user, merge and delete events are sent', function () {
    Queue::fake();
    Webhook::factory()->create(['events' => ['user.created', 'ticket.merged', 'ticket.deleted']]);

    $this->postJson(route('api.v1.users.store'), ['name' => 'Bo', 'email' => 'bo@example.com', 'invite' => false])->assertCreated();

    $source = Ticket::factory()->create();
    $target = Ticket::factory()->create();
    app(MergeTickets::class)->handle($source, $target, $this->admin);
    app(DeleteTicket::class)->handle($target);

    $payloads = WebhookDelivery::query()->oldest('id')->get()->keyBy('event')->map->payload;

    expect($payloads['user.created']['data']['user']['email'])->toBe('bo@example.com')
        ->and($payloads['ticket.merged']['data']['ticket']['id'])->toBe($source->id)
        ->and($payloads['ticket.merged']['data']['target']['id'])->toBe($target->id)
        ->and($payloads['ticket.deleted']['data']['ticket']['id'])->toBe($target->id);
});

test('managing webhooks needs the integrations permission', function () {
    Sanctum::actingAs(User::factory()->agent()->create(), ['webhooks:manage']);

    $this->getJson(route('api.v1.webhooks.index'))->assertForbidden();
});
