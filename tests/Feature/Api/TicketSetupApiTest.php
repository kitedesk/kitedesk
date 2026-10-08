<?php

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\CustomStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\CustomStatuses;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->admin()->create(), ['tickets:read', 'setup:write']);
});

test('setup writes need the setup ability and permission', function () {
    Sanctum::actingAs(User::factory()->agent()->create(), ['tickets:read', 'setup:write']);

    $this->postJson(route('api.v1.categories.store'), ['name' => 'Billing'])->assertForbidden();
});

test('categories, fields and forms can be managed', function () {
    $category = $this->postJson(route('api.v1.categories.store'), ['name' => 'Billing'])->assertCreated()->json('data.id');
    $this->postJson(route('api.v1.categories.store'), ['name' => 'Refunds', 'parent_id' => $category])->assertCreated()->assertJsonPath('data.parent_id', $category);
    $this->putJson(route('api.v1.categories.update', $category), ['name' => 'Money'])->assertOk()->assertJsonPath('data.name', 'Money');

    $field = $this->postJson(route('api.v1.ticket-fields.store'), ['label' => 'Order number', 'type' => 'text'])
        ->assertCreated()
        ->assertJsonPath('data.key', 'order_number')
        ->json('data.id');

    $form = $this->postJson(route('api.v1.ticket-forms.store'), ['name' => 'Orders', 'fields' => [['id' => $field, 'is_required' => true]]])
        ->assertCreated()
        ->assertJsonPath('data.fields', [['id' => $field, 'key' => 'order_number', 'required' => true]])
        ->json('data.id');

    $this->putJson(route('api.v1.ticket-forms.update', $form), ['name' => 'Orders', 'fields' => []])->assertOk()->assertJsonPath('data.fields', []);

    $this->deleteJson(route('api.v1.ticket-forms.destroy', $form))->assertNoContent();
    $this->deleteJson(route('api.v1.ticket-fields.destroy', $field))->assertNoContent();
    $this->deleteJson(route('api.v1.categories.destroy', $category))->assertNoContent();
});

test('deleting a status moves its tickets, and default statuses stay', function () {
    $status = $this->postJson(route('api.v1.statuses.store'), ['name' => 'Waiting on vendor', 'category' => 'pending', 'color' => CustomStatus::COLORS[0]])
        ->assertCreated()
        ->json('data.id');
    $ticket = Ticket::factory()->create(['status' => 'pending']);
    $ticket->forceFill(['ticket_status_id' => $status])->saveQuietly();

    $this->deleteJson(route('api.v1.statuses.destroy', CustomStatuses::defaultFor(TicketStatus::Pending)))->assertJsonValidationErrors('status');
    $this->deleteJson(route('api.v1.statuses.destroy', $status))->assertNoContent();

    expect($ticket->refresh()->ticket_status_id)->toBe(CustomStatuses::defaultFor(TicketStatus::Pending)->id);
});
