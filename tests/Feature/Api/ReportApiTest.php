<?php

use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('the ticket report returns totals for the range', function () {
    Ticket::factory()->count(2)->create();
    Sanctum::actingAs(User::factory()->agent()->create(), ['reports:read']);

    $this->getJson(route('api.v1.reports.tickets', ['range' => '7']))
        ->assertOk()
        ->assertJsonPath('data.totals.created', 2)
        ->assertJsonCount(7, 'data.daily')
        ->assertJsonStructure(['data' => ['range' => ['from', 'to'], 'totals' => ['solved', 'backlog', 'median_first_response_minutes', 'sla_compliance'], 'breakdowns' => ['agent', 'group', 'category', 'channel']]]);

    $this->getJson(route('api.v1.reports.tickets', ['range' => 'custom']))->assertJsonValidationErrors(['from', 'to']);
});

test('reports need a role that can view them', function () {
    Sanctum::actingAs(User::factory()->lightAgent()->create(), ['reports:read']);

    $this->getJson(route('api.v1.reports.tickets'))->assertForbidden();
});
