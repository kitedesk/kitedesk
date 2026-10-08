<?php

use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->agent()->create(), ['tickets:read', 'tickets:write']);
    $this->payload = ['requester' => ['email' => 'ana@example.com', 'name' => 'Ana'], 'subject' => 'Imported', 'body' => '<p>Hi</p>'];
});

test('repeating a request with the same idempotency key returns the first response', function () {
    $first = $this->postJson(route('api.v1.tickets.store'), $this->payload, ['Idempotency-Key' => 'crm-42'])->assertCreated();

    $this->postJson(route('api.v1.tickets.store'), $this->payload, ['Idempotency-Key' => 'crm-42'])
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(Ticket::query()->count())->toBe(1);
});

test('reusing a key for a different request is a conflict', function () {
    $this->postJson(route('api.v1.tickets.store'), $this->payload, ['Idempotency-Key' => 'crm-42'])->assertCreated();

    $this->postJson(route('api.v1.tickets.store'), [...$this->payload, 'subject' => 'Other'], ['Idempotency-Key' => 'crm-42'])->assertConflict();

    expect(Ticket::query()->count())->toBe(1);
});

test('failed requests are not remembered, and requests without a key always run', function () {
    $this->postJson(route('api.v1.tickets.store'), [...$this->payload, 'subject' => ''], ['Idempotency-Key' => 'crm-42'])->assertUnprocessable();
    $this->postJson(route('api.v1.tickets.store'), $this->payload, ['Idempotency-Key' => 'crm-42'])->assertCreated();

    $this->postJson(route('api.v1.tickets.store'), $this->payload)->assertCreated();
    $this->postJson(route('api.v1.tickets.store'), $this->payload)->assertCreated();

    expect(Ticket::query()->count())->toBe(3);
});
