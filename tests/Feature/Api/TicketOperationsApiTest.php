<?php

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Accounts\Models\Group;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\SatisfactionRating;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->agent = User::factory()->agent()->create();
    $this->assignedOnly = User::factory()->withPermissions([Permission::UpdateTickets], TicketAccess::Assigned)->create();
    Sanctum::actingAs($this->agent, ['tickets:read', 'tickets:write', 'tickets:merge', 'tickets:delete']);
});

test('people can be copied on a ticket and removed', function () {
    $ticket = Ticket::factory()->create();

    $this->postJson(route('api.v1.tickets.collaborators.store', $ticket), ['email' => 'Boss@example.com'])
        ->assertOk()
        ->assertJsonPath('data.collaborators.0.email', 'boss@example.com');

    $boss = User::query()->where('email', 'boss@example.com')->sole();

    $this->deleteJson(route('api.v1.tickets.collaborators.destroy', [$ticket, $boss]))
        ->assertOk()
        ->assertJsonCount(0, 'data.collaborators');
});

test('single tags can be added and removed', function () {
    $ticket = Ticket::factory()->create();

    $this->putJson(route('api.v1.tickets.tags.update', [$ticket, 'vip']))->assertOk()->assertJsonPath('data.tags', ['vip']);
    $this->putJson(route('api.v1.tickets.tags.update', [$ticket, 'billing']))->assertOk()->assertJsonCount(2, 'data.tags');
    $this->deleteJson(route('api.v1.tickets.tags.destroy', [$ticket, 'vip']))->assertOk()->assertJsonPath('data.tags', ['billing']);
});

test('tickets can be linked, listed and unlinked', function () {
    $ticket = Ticket::factory()->create();
    $other = Ticket::factory()->create();

    $this->postJson(route('api.v1.tickets.links.store', $ticket), ['linked_id' => $other->id])
        ->assertOk()
        ->assertJsonPath('data.0.id', $other->id);

    $this->getJson(route('api.v1.tickets.links.index', $other))->assertJsonPath('data.0.id', $ticket->id);

    $this->deleteJson(route('api.v1.tickets.links.destroy', [$ticket, $other]))->assertNoContent();
    $this->getJson(route('api.v1.tickets.links.index', $ticket))->assertJsonCount(0, 'data');
});

test('tickets can be merged and forwarded', function () {
    $duplicate = Ticket::factory()->create();
    $target = Ticket::factory()->create();
    $group = Group::factory()->create();

    $this->postJson(route('api.v1.tickets.merge', $duplicate), ['target_id' => $target->id])
        ->assertOk()
        ->assertJsonPath('data.id', $target->id);

    expect($duplicate->refresh()->status)->toBe(TicketStatus::Closed);

    $this->postJson(route('api.v1.tickets.forward', $target), ['group_id' => $group->id, 'note' => '<p>Billing question</p>'])
        ->assertOk()
        ->assertJsonPath('data.group.id', $group->id);
});

test('merging and deleting need their own ability and permission', function () {
    $ticket = Ticket::factory()->create();

    Sanctum::actingAs($this->agent, ['tickets:read', 'tickets:write']);
    $this->postJson(route('api.v1.tickets.merge', $ticket), ['target_id' => Ticket::factory()->create()->id])->assertForbidden();

    $this->deleteJson(route('api.v1.tickets.destroy', $ticket))->assertForbidden();

    Sanctum::actingAs(User::factory()->admin()->create(), ['tickets:delete']);
    $this->deleteJson(route('api.v1.tickets.destroy', $ticket))->assertNoContent();

    expect(Ticket::query()->find($ticket->id))->toBeNull();
});

test('attachments download with the same token', function () {
    Storage::fake('local');
    $ticket = Ticket::factory()->create();

    $this->post(route('api.v1.tickets.messages.store', $ticket), [
        'body' => '<p>Log attached</p>',
        'is_internal' => true,
        'attachments' => [UploadedFile::fake()->create('log.txt', 1, 'text/plain')],
    ], ['Accept' => 'application/json'])->assertCreated();

    $url = collect($this->getJson(route('api.v1.tickets.messages.index', $ticket))->json('data'))->last()['attachments'][0]['url'];

    expect($url)->toStartWith(route('api.v1.attachments.show', TicketMessage::query()->latest('id')->first()->getFirstMedia('attachments')));
    $this->get($url)->assertOk()->assertDownload('log.txt');

    Sanctum::actingAs($this->assignedOnly, ['tickets:read']);
    $this->get($url)->assertForbidden();
});

test('lookups list groups, statuses, categories, fields, forms and tags', function () {
    $group = Group::factory()->create();
    $group->agents()->attach($this->agent);
    $ticket = Ticket::factory()->create();
    $this->putJson(route('api.v1.tickets.tags.update', [$ticket, 'vip']));

    $this->getJson(route('api.v1.groups.index'))->assertOk()->assertJsonPath('data.0.agent_ids', [$this->agent->id]);
    $this->getJson(route('api.v1.statuses.index'))->assertOk()->assertJsonStructure(['data' => [['id', 'name', 'category', 'is_default']]]);
    $this->getJson(route('api.v1.categories.index'))->assertOk();
    $this->getJson(route('api.v1.ticket-fields.index'))->assertOk();
    $this->getJson(route('api.v1.ticket-forms.index'))->assertOk();
    $this->getJson(route('api.v1.tags.index', ['filter' => ['search' => 'vi']]))
        ->assertOk()
        ->assertJsonPath('data.0', ['id' => $ticket->tags()->sole()->id, 'name' => 'vip', 'tickets_count' => 1]);
});

test('satisfaction ratings are listed for visible tickets only', function () {
    $assignedOnly = $this->assignedOnly;
    $mine = SatisfactionRating::factory()->create(['ticket_id' => Ticket::factory()->create(['assignee_id' => $assignedOnly->id])->id, 'score' => 5, 'rated_at' => now()]);
    SatisfactionRating::factory()->create(['score' => 1, 'rated_at' => now()]);

    $this->getJson(route('api.v1.satisfaction-ratings.index'))->assertJsonCount(2, 'data');
    $this->getJson(route('api.v1.satisfaction-ratings.index', ['filter' => ['score' => 1]]))->assertJsonCount(1, 'data');

    Sanctum::actingAs($assignedOnly, ['tickets:read']);
    $this->getJson(route('api.v1.satisfaction-ratings.index'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
});
