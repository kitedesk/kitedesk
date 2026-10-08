<?php

use App\Domain\Support\PublicNetwork;
use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Actions\CreateTicket;
use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Webhooks\Models\Webhook;
use App\Domain\Webhooks\Models\WebhookDelivery;
use App\Http\Resources\Api\V1\MessageResource;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('creating a webhook generates a secret shown once', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.webhooks.store'), ['name' => 'CRM', 'url' => 'https://crm.test/hook', 'events' => ['ticket.created'], 'is_active' => true])
        ->assertRedirect()
        ->assertInertiaFlash('webhookSecret');

    expect(Webhook::query()->sole()->secret)->toHaveLength(40);
});

test('ticket events are delivered with a valid signature', function () {
    Http::fake(['crm.test/*' => Http::response('ok')]);
    $webhook = Webhook::factory()->create(['url' => 'https://crm.test/hook', 'events' => ['ticket.created']]);

    $ticket = app(CreateTicket::class)->handle(User::factory()->create(), ['subject' => 'Hello', 'body' => '<p>Hi</p>'], TicketChannel::Api);

    Http::assertSent(function (Request $request) use ($webhook, $ticket): bool {
        $expected = 'sha256='.hash_hmac('sha256', $request->header('X-Support-Timestamp')[0].'.'.$request->body(), $webhook->secret);

        return $request->url() === 'https://crm.test/hook'
            && $request->header('X-Support-Event')[0] === 'ticket.created'
            && $request->header('X-Support-Signature')[0] === $expected
            && $request['id'] === $request->header('X-Support-Delivery')[0]
            && $request['data']['ticket']['id'] === $ticket->id;
    });

    expect(WebhookDelivery::query()->sole()->delivered_at)->not->toBeNull();
});

test('webhook payloads use the same shapes as the API', function () {
    Http::fake();
    Webhook::factory()->create(['events' => ['ticket.created', 'ticket.updated', 'message.created']]);
    $ticket = app(CreateTicket::class)->handle(User::factory()->create(), ['subject' => 'Hello', 'body' => '<p>Hi</p>'], TicketChannel::Api);
    app(UpdateTicket::class)->handle($ticket, ['priority' => 'urgent'], $this->admin);
    $reply = app(AddMessage::class)->handle($ticket, $this->admin, '<p>On it</p>');

    // The first delivery of each event: the reply also updates the ticket.
    $payloads = WebhookDelivery::query()->oldest('id')->get()->unique('event')->keyBy('event')->map->payload;
    $this->actingAs($this->admin);
    $asJson = fn (array $data): array => json_decode((string) json_encode($data), true);
    $apiTicket = $asJson((new TicketResource($ticket->refresh()))->resolve());

    expect($payloads['ticket.updated']['data']['ticket']['id'])->toBe($ticket->id)
        ->and(array_keys($payloads['ticket.updated']['data']['ticket']))->toBe(array_keys($apiTicket))
        ->and($payloads['ticket.updated']['changes']['priority']['to'])->toBe('urgent')
        ->and($payloads['ticket.created'])->not->toHaveKey('changes')
        ->and($payloads['message.created']['data']['message'])->toEqual($asJson((new MessageResource($reply))->resolve()));
});

test('webhooks only receive the events they subscribe to', function () {
    Http::fake();
    Webhook::factory()->create(['events' => ['ticket.solved']]);

    app(CreateTicket::class)->handle(User::factory()->create(), ['subject' => 'Hello', 'body' => '<p>Hi</p>'], TicketChannel::Api);

    Http::assertNothingSent();
});

test('failed deliveries record the response and can be redelivered', function () {
    Http::fake(['*' => Http::sequence()->push('nope', 500)->push('ok')]);
    $webhook = Webhook::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.webhooks.test', $webhook));

    $delivery = WebhookDelivery::query()->sole();
    expect($delivery->response_status)->toBe(500)
        ->and($delivery->delivered_at)->toBeNull();

    $this->actingAs($this->admin)->post(route('admin.webhooks.deliveries.redeliver', [$webhook, $delivery]));

    expect(WebhookDelivery::query()->whereNotNull('delivered_at')->count())->toBe(1);
});

test('rotating the secret invalidates the old one', function () {
    $webhook = Webhook::factory()->create();
    $old = $webhook->secret;

    $this->actingAs($this->admin)->post(route('admin.webhooks.secret', $webhook))->assertInertiaFlash('webhookSecret');

    expect($webhook->refresh()->secret)->not->toBe($old);
});

test('webhook URLs must point to a public address', function (string $url, array $resolved) {
    PublicNetwork::resolveUsing(fn (string $host): array => $resolved);

    $this->actingAs($this->admin)
        ->post(route('admin.webhooks.store'), ['name' => 'Internal', 'url' => $url, 'events' => ['ticket.created']])
        ->assertSessionHasErrors('url');

    expect(Webhook::query()->count())->toBe(0);
})->with([
    'loopback' => ['http://127.0.0.1:8080/hook', []],
    'cloud metadata' => ['http://169.254.169.254/latest/meta-data', []],
    'IPv6 loopback' => ['http://[::1]/hook', []],
    'name resolving to a private network' => ['https://intranet.example.com/hook', ['10.0.0.5']],
    'name resolving to CGNAT' => ['https://cgnat.example.com/hook', ['100.64.1.2']],
    'IPv4-mapped IPv6' => ['https://mapped.example.com/hook', ['::ffff:127.0.0.1']],
    'unresolvable name' => ['https://nowhere.invalid/hook', []],
]);

test('private targets can be allowed for intranet installs', function () {
    config(['kitedesk.webhooks.allow_private_targets' => true]);

    $this->actingAs($this->admin)
        ->post(route('admin.webhooks.store'), ['name' => 'Intranet', 'url' => 'http://10.0.0.5/hook', 'events' => ['ticket.created']])
        ->assertSessionHasNoErrors();
});

test('deliveries to a host that now resolves internally are blocked without retrying', function () {
    Http::fake();
    $webhook = Webhook::factory()->create(['url' => 'https://crm.test/hook', 'events' => ['ticket.created']]);
    PublicNetwork::resolveUsing(fn (string $host): array => ['192.168.1.10']);

    app(CreateTicket::class)->handle(User::factory()->create(), ['subject' => 'Hello', 'body' => '<p>Hi</p>'], TicketChannel::Api);

    Http::assertNothingSent();
    expect($webhook->deliveries()->sole())
        ->failed_at->not->toBeNull()
        ->delivered_at->toBeNull()
        ->attempts->toBe(1);
});

test('a redirect response is not treated as delivered', function () {
    Http::fake(['crm.test/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/'])]);
    Webhook::factory()->create(['url' => 'https://crm.test/hook', 'events' => ['ticket.created']]);

    rescue(fn () => app(CreateTicket::class)->handle(User::factory()->create(), ['subject' => 'Hello', 'body' => '<p>Hi</p>'], TicketChannel::Api), report: false);

    expect(WebhookDelivery::query()->sole())
        ->response_status->toBe(302)
        ->delivered_at->toBeNull();
});

test('old webhook deliveries are pruned', function () {
    $webhook = Webhook::factory()->create();
    $old = $webhook->deliveries()->create(['uuid' => (string) Str::uuid(), 'event' => 'ticket.created', 'payload' => []]);
    $old->forceFill(['created_at' => now()->subDays(WebhookDelivery::RETENTION_DAYS + 1)])->save();
    $recent = $webhook->deliveries()->create(['uuid' => (string) Str::uuid(), 'event' => 'ticket.created', 'payload' => []]);

    $this->artisan('model:prune', ['--model' => [WebhookDelivery::class]])->assertSuccessful();

    expect(WebhookDelivery::query()->pluck('id')->all())->toBe([$recent->id]);
});
