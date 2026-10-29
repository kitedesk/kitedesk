<?php

use App\Domain\Support\Sidebar\SidebarSettings;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the sidebar uses menus until an admin picks another default', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)->get(route('agent.tickets.index'))
        ->assertInertia(fn (Assert $page) => $page->where('sidebar.mode', 'menus')->where('sidebar.default', 'menus')->where('sidebar.preference', null));

    $this->actingAs(User::factory()->admin()->create())->put(route('admin.sidebar.update'), ['mode' => 'legacy'])->assertSessionHasNoErrors();

    $this->actingAs($agent)->get(route('agent.tickets.index'))
        ->assertInertia(fn (Assert $page) => $page->where('sidebar.mode', 'legacy')->where('sidebar.default', 'legacy'));
});

test('an agent can override the default for themselves and go back to it', function () {
    SidebarSettings::saveDefault('legacy');
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)->put(route('sidebar-preference.update'), ['mode' => 'menus'])->assertRedirect();

    expect(SidebarSettings::for($agent->fresh()))->toBe('menus')
        ->and(SidebarSettings::default())->toBe('legacy');

    $this->actingAs($agent)->put(route('sidebar-preference.update'), ['mode' => 'default']);

    expect(SidebarSettings::for($agent->fresh()))->toBe('legacy');
    $this->actingAs($agent)->put(route('sidebar-preference.update'), ['mode' => 'wide'])->assertSessionHasErrors('mode');
});

test('keeping the other preferences when the sidebar choice changes', function () {
    $agent = User::factory()->agent()->create(['preferences' => ['reply_status_id' => 4]]);

    $this->actingAs($agent)->put(route('sidebar-preference.update'), ['mode' => 'legacy']);

    expect($agent->fresh()->preferences)->toBe(['reply_status_id' => 4, 'sidebar' => 'legacy']);
});

test('only admins with the branding permission set the default, and customers have no preference', function () {
    $this->actingAs(User::factory()->agent()->create())->put(route('admin.sidebar.update'), ['mode' => 'legacy'])->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.sidebar.edit'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/sidebar/edit')->where('mode', 'menus'));
    $this->actingAs(User::factory()->admin()->create())->put(route('admin.sidebar.update'), ['mode' => 'wide'])->assertSessionHasErrors('mode');

    $this->actingAs(User::factory()->create())->put(route('sidebar-preference.update'), ['mode' => 'legacy'])->assertForbidden();
});
