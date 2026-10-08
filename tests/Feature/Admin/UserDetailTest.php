<?php

use App\Domain\Accounts\Models\Role;
use App\Domain\Accounts\Support\RoleCatalog;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('the user page shows their details, tickets, security and activity', function () {
    $agent = User::factory()->agent()->withTwoFactor()->create(['job_title' => 'Support lead', 'timezone' => 'America/Sao_Paulo']);
    Ticket::factory()->count(2)->create(['assignee_id' => $agent->id, 'status' => TicketStatus::Open]);
    Ticket::factory()->create(['assignee_id' => $agent->id, 'status' => TicketStatus::Solved, 'solved_at' => now()->subDays(3)]);

    $this->actingAs($this->admin)->put(route('admin.users.update', $agent), [
        'name' => $agent->name,
        'email' => $agent->email,
        'type' => 'staff',
        'role_id' => Role::findByName(RoleCatalog::LIGHT_AGENT)->id,
        'job_title' => 'Support lead',
        'timezone' => 'America/Sao_Paulo',
    ])->assertRedirect(route('admin.users.show', $agent));

    $this->actingAs($this->admin)->get(route('admin.users.show', $agent))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/show')
            ->where('user.job_title', 'Support lead')
            ->where('user.invited', true)
            ->where('stats.assigned_open', 2)
            ->where('stats.solved_recently', 1)
            ->missing('tickets')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('tickets', 3)
                ->where('security.two_factor_enabled', true)
                ->where('activity.0.event', 'updated')
                ->where('activity.0.causer', $this->admin->name)
                ->where('activity.0.changes.old', ['role' => 'Agent'])
                ->where('activity.0.changes.new', ['role' => 'Light agent'])));
});

test('only people who manage the team can open user pages', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)->get(route('admin.users.show', $this->admin))->assertForbidden();
});

test('admins can sign someone out everywhere and reset their two-factor sign-in', function () {
    config(['session.driver' => 'database']);
    $agent = User::factory()->agent()->withTwoFactor()->create();
    DB::table('sessions')->insert(['id' => 'agent-session', 'user_id' => $agent->id, 'ip_address' => null, 'user_agent' => '', 'payload' => '', 'last_activity' => now()->timestamp]);
    DB::table('passkeys')->insert(['user_id' => $agent->id, 'name' => 'Laptop', 'credential_id' => 'abc', 'credential' => '{}', 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($this->admin)->delete(route('admin.users.sessions.destroy', $agent))->assertRedirect();
    expect(DB::table('sessions')->where('user_id', $agent->id)->exists())->toBeFalse();

    $this->actingAs($this->admin)->delete(route('admin.users.two-factor.destroy', $agent))->assertRedirect();
    expect($agent->refresh()->hasEnabledTwoFactorAuthentication())->toBeFalse()
        ->and($agent->passkeys()->exists())->toBeFalse();

    $this->actingAs($this->admin)->delete(route('admin.users.two-factor.destroy', $this->admin))->assertUnprocessable();
});

test('deactivated people have their own tab and are left out of the others', function () {
    $active = User::factory()->agent()->create();
    $deactivated = User::factory()->agent()->deactivated()->create();

    $this->actingAs($this->admin)->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('deactivatedCount', 1)
            ->where('users.data', fn ($users) => collect($users)->pluck('id')->contains($active->id)
                && ! collect($users)->pluck('id')->contains($deactivated->id)));

    $this->actingAs($this->admin)->get(route('admin.users.index', ['audience' => 'deactivated']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $deactivated->id)
            ->where('users.data.0.deactivated', true));
});

test('agents get their signature on the ticket page', function () {
    $agent = User::factory()->agent()->create(['signature' => 'Ana · Support']);
    $ticket = Ticket::factory()->create(['assignee_id' => $agent->id]);

    $this->actingAs($agent)->get(route('agent.tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page->where('signature', 'Ana · Support'));
});
