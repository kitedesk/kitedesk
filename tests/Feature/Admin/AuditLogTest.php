<?php

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Models\Role;
use App\Domain\Ai\Support\AiSettings;
use App\Domain\Entitlements\Contracts\Entitlements;
use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Enums\Limit;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Support\Models\Setting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('role changes are recorded with the values and permissions that changed', function () {
    $this->actingAs($this->admin)->post(route('admin.roles.store'), [
        'name' => 'Tier 1', 'ticket_access' => 'groups', 'permissions' => [Permission::ReplyToTickets->value],
    ])->assertSessionHasNoErrors();
    $role = Role::findByName('Tier 1');

    $this->actingAs($this->admin)->put(route('admin.roles.update', $role), [
        'name' => 'Tier one', 'ticket_access' => 'groups', 'permissions' => [Permission::ViewReports->value],
    ])->assertSessionHasNoErrors();

    $updated = Activity::query()->forSubject($role)->where('event', 'updated')->sole();
    $permissions = Activity::query()->forSubject($role)->where('event', 'permissions_changed')->latest('id')->first();

    expect($updated->log_name)->toBe('admin')
        ->and($updated->causer->is($this->admin))->toBeTrue()
        ->and($updated->attribute_changes->toArray())->toBe(['attributes' => ['name' => 'Tier one'], 'old' => ['name' => 'Tier 1']])
        ->and($updated->properties->get('ip'))->toBe('127.0.0.1')
        ->and($permissions->properties->only(['added', 'removed'])->all())->toBe(['added' => ['reports.view'], 'removed' => ['tickets.reply']]);
});

test('credentials are never recorded', function () {
    $mailbox = Mailbox::factory()->create();
    $mailbox->update(['imap_password' => 'new secret', 'name' => 'Support']);

    $changes = Activity::query()->forSubject($mailbox)->get()->pluck('attribute_changes')->toJson();

    expect($changes)->toContain('Support')
        ->not->toContain('secret');
});

test('settings changes are recorded, with secrets only marked as set', function () {
    $this->actingAs($this->admin);
    Setting::put(AiSettings::SETTING, ['enabled' => false, 'api_key' => null, 'model' => null]);
    Setting::put(AiSettings::SETTING, ['enabled' => true, 'api_key' => 'encrypted-key', 'model' => 'claude']);

    $activity = Activity::query()->where('event', 'settings_updated')->latest('id')->first();

    expect($activity->log_name)->toBe('settings')
        ->and($activity->properties->get('setting'))->toBe('ai')
        ->and($activity->attribute_changes->get('attributes'))->toBe(['enabled' => true, 'api_key' => '********', 'model' => 'claude'])
        ->and($activity->toJson())->not->toContain('encrypted-key');
});

test('signing in, failing to and signing out are recorded', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
    $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'wrong']);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('logout'));

    $events = Activity::query()->where('log_name', 'auth')->orderBy('id')->get();

    expect($events->pluck('event')->all())->toBe(['login_failed', 'login_failed', 'login', 'logout'])
        ->and($events[0]->subject->is($user))->toBeTrue()
        ->and($events[0]->causer)->toBeNull()
        ->and($events[1]->subject)->toBeNull()
        ->and($events[1]->properties->get('email'))->toBe('nobody@example.com')
        ->and($events[2]->properties->get('ip'))->toBe('127.0.0.1');
});

test('admins filter the audit log and export it', function () {
    $agent = User::factory()->agent()->create();
    $this->actingAs($agent)->put(route('user-password.update'), [
        'current_password' => 'password', 'password' => 'a new password', 'password_confirmation' => 'a new password',
    ]);
    $this->actingAs($this->admin)->post(route('admin.users.password.require-change', $agent));

    $this->actingAs($this->admin)->get(route('admin.audit-log.index', ['category' => 'auth', 'user' => $agent->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/audit-log/index')
            ->has('entries.data', 1)
            ->where('entries.data.0.event', 'password_changed')
            ->where('entries.data.0.actor.name', $agent->name)
            ->where('entries.data.0.target.url', route('admin.users.show', $agent)));

    $this->actingAs($this->admin)->get(route('admin.audit-log.index', ['from' => now()->addDay()->toDateString()]))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 0));

    $csv = $this->actingAs($this->admin)->get(route('admin.audit-log.export', ['user' => $agent->id]))->assertOk()->streamedContent();

    expect($csv)->toContain('Asked for a new password')
        ->toContain('Changed their password')
        ->toContain($agent->name);
});

test('the audit log needs its permission and a plan that includes it', function () {
    $this->actingAs(User::factory()->agent()->create())->get(route('admin.audit-log.index'))->assertForbidden();

    app()->instance(Entitlements::class, new class implements Entitlements
    {
        public function allows(Feature $feature): bool
        {
            return $feature !== Feature::AuditLog;
        }

        public function limit(Limit $limit): ?int
        {
            return null;
        }
    });

    $this->actingAs($this->admin)->get(route('admin.audit-log.index'))->assertForbidden();
    // Recording goes on regardless.
    $this->actingAs($this->admin)->post(route('admin.users.password.require-change', User::factory()->create()));
    expect(Activity::query()->where('event', 'password_change_required')->exists())->toBeTrue();
});
