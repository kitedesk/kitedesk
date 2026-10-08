<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('staff are sent to the agent workspace', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('agent.tickets.index'));
});

test('customers are sent to their requests', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('portal.tickets.index'));
});
