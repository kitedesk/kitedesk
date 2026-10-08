<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('unauthenticated requests are rejected', function () {
    $this->getJson(route('api.v1.tickets.index'))->assertUnauthorized();
});

test('customer tokens cannot use the API', function () {
    Sanctum::actingAs(User::factory()->create(), ['tickets:read']);

    $this->getJson(route('api.v1.tickets.index'))->assertForbidden();
});

test('tokens need the ability required by the endpoint', function () {
    Sanctum::actingAs(User::factory()->agent()->create(), ['kb:read']);

    $this->getJson(route('api.v1.tickets.index'))->assertForbidden();
    $this->getJson(route('api.v1.help-center.articles.index'))->assertOk();
});

test('read-only tokens cannot write', function () {
    Sanctum::actingAs(User::factory()->agent()->create(), ['tickets:read']);

    $this->postJson(route('api.v1.tickets.store'), [])->assertForbidden();
});

test('real tokens authenticate with the bearer header', function () {
    $token = User::factory()->agent()->create()->createToken('test', ['tickets:read'])->plainTextToken;

    $this->withToken($token)->getJson(route('api.v1.tickets.index'))->assertOk();
});
