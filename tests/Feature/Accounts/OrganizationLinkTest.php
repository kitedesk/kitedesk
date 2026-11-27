<?php

use App\Domain\Accounts\Enums\OrganizationRole;
use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Accounts\Models\Organization;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Webhooks\Models\Webhook;
use App\Domain\Webhooks\Models\WebhookDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->agent = User::factory()->agent()->create(['name' => 'Ana Agent']);
    $this->acme = Organization::factory()->create(['name' => 'Acme', 'domains' => ['acme.test']]);
    $this->jane = User::factory()->create(['name' => 'Jane', 'email' => 'jane@acme.test']);
    $this->ticket = Ticket::factory()->for($this->jane, 'requester')->create();
});

function linkRequester(Ticket $ticket, ?int $organizationId, ?string $role = null): TestResponse
{
    return test()->put(route('agent.tickets.requester.organization', $ticket), ['organization_id' => $organizationId, 'role' => $role]);
}

test('a matching email domain is offered as a suggestion, not assumed', function () {
    expect($this->ticket->organization_id)->toBeNull();

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.show', $this->ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requester.organization', null)
            ->where('requester.suggested_organization', ['id' => $this->acme->id, 'name' => 'Acme'])
            ->where('requester.can_link_organization', true));
});

test('agents confirm a customer as an authorized contact, and their open tickets follow', function () {
    Queue::fake();
    Webhook::factory()->create(['events' => ['user.updated']]);
    $solved = Ticket::factory()->for($this->jane, 'requester')->status(TicketStatus::Solved)->create();

    $this->actingAs($this->agent);
    linkRequester($this->ticket, $this->acme->id, 'authorized_contact')->assertSessionHasNoErrors();

    $jane = $this->jane->refresh();
    expect($jane->organization_id)->toBe($this->acme->id)
        ->and($jane->organization_role)->toBe(OrganizationRole::AuthorizedContact)
        ->and($jane->organization_linked_by)->toBe($this->agent->id)
        ->and($this->ticket->refresh()->organization_id)->toBe($this->acme->id)
        ->and($solved->refresh()->organization_id)->toBeNull();

    $this->get(route('agent.tickets.show', $this->ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requester.organization.name', 'Acme')
            ->where('requester.organization_role', 'authorized_contact')
            ->where('requester.organization_linked.by', 'Ana Agent')
            ->where('requester.suggested_organization', null));

    $audit = Activity::query()->where('subject_type', $jane->getMorphClass())->where('subject_id', $jane->id)->sole();
    expect($audit->attribute_changes['attributes'])->toBe(['organization' => 'Acme', 'organization_role' => 'Authorized contact']);

    expect(WebhookDelivery::query()->sole()->payload['changes']['organization_role'])->toBe(['from' => null, 'to' => 'Authorized contact']);
});

test('removing a customer from their organization clears it from their open tickets', function () {
    $jane = User::factory()->inOrganization($this->acme, OrganizationRole::AuthorizedContact)->create();
    $ticket = Ticket::factory()->for($jane, 'requester')->create(['organization_id' => $this->acme->id]);

    $this->actingAs($this->agent);
    linkRequester($ticket, null)->assertSessionHasNoErrors();

    expect($jane->refresh()->organization_id)->toBeNull()
        ->and($jane->organization_role)->toBeNull()
        ->and($ticket->refresh()->organization_id)->toBeNull();
});

test('linking needs the permission, and only customers can be linked', function () {
    $this->actingAs(User::factory()->withPermissions([Permission::UpdateTickets])->create());
    linkRequester($this->ticket, $this->acme->id)->assertForbidden();

    $internal = Ticket::factory()->for(User::factory()->agent()->create(), 'requester')->create();
    $this->actingAs($this->agent);
    linkRequester($internal, $this->acme->id)->assertSessionHasErrors('organization_id');
});

test('agents only link requesters of tickets they can see', function () {
    $hidden = Ticket::factory()->create();
    $restricted = User::factory()->withPermissions([Permission::LinkCustomers], TicketAccess::Assigned)->create();

    $this->actingAs($restricted);
    linkRequester($hidden, $this->acme->id)->assertForbidden();
});

test('the organization search matches names', function () {
    Organization::factory()->create(['name' => 'Globex']);

    $this->actingAs($this->agent)
        ->getJson(route('agent.organizations.lookup', ['q' => 'acm']))
        ->assertOk()
        ->assertExactJson(['organizations' => [['id' => $this->acme->id, 'name' => 'Acme']]]);
});

test('admins set the role on the user form, and the people list filters by organization', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['name' => 'Outsider']);

    $this->actingAs($admin)->put(route('admin.users.update', $this->jane), [
        'name' => 'Jane',
        'email' => 'jane@acme.test',
        'type' => 'customer',
        'organization_id' => $this->acme->id,
        'organization_role' => 'authorized_contact',
    ])->assertSessionHasNoErrors();

    expect($this->jane->refresh()->organization_role)->toBe(OrganizationRole::AuthorizedContact)
        ->and($this->ticket->refresh()->organization_id)->toBe($this->acme->id);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['audience' => 'customers', 'organization' => $this->acme->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.organization_role', 'Authorized contact')
            ->where('organization.name', 'Acme'));
});

test('the API sets and returns the role; it is kept until they move to another organization', function () {
    Sanctum::actingAs(User::factory()->admin()->create(), ['users:write', 'users:read']);
    $globex = Organization::factory()->create();

    $this->putJson(route('api.v1.users.update', $this->jane), ['name' => 'Jane', 'email' => 'jane@acme.test', 'organization_id' => $this->acme->id, 'organization_role' => 'authorized_contact'])
        ->assertOk()
        ->assertJsonPath('data.organization_role', 'authorized_contact');

    $this->putJson(route('api.v1.users.update', $this->jane), ['name' => 'Jane', 'email' => 'jane@acme.test', 'organization_id' => $this->acme->id])
        ->assertJsonPath('data.organization_role', 'authorized_contact');

    $this->putJson(route('api.v1.users.update', $this->jane), ['name' => 'Jane', 'email' => 'jane@acme.test', 'organization_id' => $globex->id])
        ->assertJsonPath('data.organization.id', $globex->id)
        ->assertJsonPath('data.organization_role', 'member');
});
