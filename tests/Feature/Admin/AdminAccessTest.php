<?php

use App\Models\User;

test('only administrators can open the admin center', function () {
    $this->actingAs(User::factory()->agent()->create())->get(route('admin.index'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('admin.index'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.index'))->assertOk();
});
