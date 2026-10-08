<?php

use App\Models\User;

test('the home page sends visitors to the help center', function () {
    User::factory()->admin()->create();

    $response = $this->get(route('home'));

    $response->assertRedirect(route('help.index'));
});
