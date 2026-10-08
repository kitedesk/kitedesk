<?php

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\GuestAccess;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('deactivating someone ends their sessions and keeps them out', function () {
    config(['session.driver' => 'database']);
    $agent = User::factory()->agent()->create();
    DB::table('sessions')->insert(['id' => 'agent-session', 'user_id' => $agent->id, 'ip_address' => null, 'user_agent' => '', 'payload' => '', 'last_activity' => now()->timestamp]);

    $this->actingAs($this->admin)->post(route('admin.users.deactivate', $agent))->assertSessionHasNoErrors();

    expect($agent->refresh()->isDeactivated())->toBeTrue()
        ->and($agent->is_available)->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $agent->id)->exists())->toBeFalse();
    $this->assertDatabaseHas('activity_log', ['subject_id' => $agent->id, 'event' => 'deactivated', 'causer_id' => $this->admin->id]);

    // A session that was still open is ended on the next request.
    $this->actingAs($agent)->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'This account has been deactivated.');
    $this->assertGuest();
});

test('deactivated people cannot sign in with a password or a passkey, and get no reset links', function () {
    Mail::fake();
    $agent = User::factory()->agent()->deactivated()->create();

    $this->post(route('login.store'), ['email' => $agent->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    $passkey = (new Passkey)->setRelation('user', $agent);
    expect(Passkeys::allowsLogin(Request::create('/'), $passkey))->toBeFalse()
        ->and(Passkeys::allowsLogin(Request::create('/'), (new Passkey)->setRelation('user', $this->admin)))->toBeTrue();

    $this->post(route('password.email'), ['email' => $agent->email]);
    Mail::assertNothingOutgoing();

    $this->post(route('password.update'), [
        'token' => Password::createToken($agent),
        'email' => $agent->email,
        'password' => 'a-new-password-123',
        'password_confirmation' => 'a-new-password-123',
    ])->assertSessionHasErrors('email');
});

test('deactivated team members lose API access and stop getting tickets', function () {
    $agent = User::factory()->agent()->create();
    $token = $agent->createToken('test', ['tickets:read'])->plainTextToken;

    $this->actingAs($this->admin)->post(route('admin.users.deactivate', $agent));
    auth()->forgetGuards();

    $this->withToken($token)->getJson(route('api.v1.tickets.index'))->assertForbidden();
    expect(User::query()->assignable()->whereKey($agent->id)->exists())->toBeFalse();

    $this->actingAs($this->admin)->post(route('admin.users.reactivate', $agent))->assertSessionHasNoErrors();
    auth()->forgetGuards();

    $this->withToken($token)->getJson(route('api.v1.tickets.index'))->assertOk();
    expect(User::query()->assignable()->whereKey($agent->id)->exists())->toBeTrue();
});

test('deactivated customers lose their emailed ticket links', function () {
    $customer = User::factory()->create();
    $ticket = Ticket::factory()->create(['requester_id' => $customer->id]);

    $customer->forceFill(['deactivated_at' => now()])->save();

    $this->get(GuestAccess::linkFor($ticket, $customer))->assertForbidden();
});

test('people cannot deactivate themselves or the last administrator', function () {
    $this->actingAs($this->admin)->post(route('admin.users.deactivate', $this->admin))
        ->assertSessionHasErrors(['user' => 'You cannot deactivate your own account.']);

    $otherAdmin = User::factory()->admin()->create();
    $this->actingAs($this->admin)->post(route('admin.users.deactivate', $otherAdmin))->assertSessionHasNoErrors();

    $this->actingAs(User::factory()->withPermissions([Permission::ManageTeam])->create())
        ->post(route('admin.users.deactivate', $this->admin))
        ->assertSessionHasErrors(['user' => 'The help desk needs at least one administrator.']);

    expect($this->admin->refresh()->isDeactivated())->toBeFalse();
});
