<?php

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\CustomStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Support\CustomStatuses;
use App\Domain\Workflows\Enums\WorkflowTrigger;
use App\Domain\Workflows\Models\Workflow;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('every category starts with a default status', function () {
    $defaults = CustomStatus::query()->where('is_default', true)->get();

    expect($defaults->pluck('category')->all())->toEqualCanonicalizing(TicketStatus::cases())
        ->and($defaults->every(fn (CustomStatus $status): bool => $status->name === null))->toBeTrue()
        ->and(CustomStatuses::defaultFor(TicketStatus::OnHold)->label())->toBe('On-hold');
});

test('admins add statuses at the end of their category and reorder them', function () {
    $this->actingAs($this->admin)->post(route('admin.ticket-statuses.store'), [
        'name' => 'Waiting on vendor',
        'category' => 'pending',
        'color' => 'violet',
    ])->assertSessionHasNoErrors();

    $status = CustomStatus::query()->where('name', 'Waiting on vendor')->sole();
    expect($status->category)->toBe(TicketStatus::Pending)
        ->and($status->position)->toBe(1)
        ->and($status->is_default)->toBeFalse();

    $this->actingAs($this->admin)->post(route('admin.ticket-statuses.move', $status), ['direction' => 'up']);

    expect(CustomStatuses::all()->where('category', TicketStatus::Pending)->pluck('id')->first())->toBe($status->id);
});

test('a status in use keeps its category', function () {
    $status = CustomStatus::factory()->category(TicketStatus::Pending)->create();
    Ticket::factory()->create(['ticket_status_id' => $status->id]);

    $this->actingAs($this->admin)->put(route('admin.ticket-statuses.update', $status), [
        'name' => 'Renamed',
        'category' => 'open',
        'color' => 'blue',
    ])->assertSessionHasErrors('category');

    $this->actingAs($this->admin)->put(route('admin.ticket-statuses.update', $status), [
        'name' => 'Renamed',
        'category' => 'pending',
        'color' => 'blue',
    ])->assertSessionHasNoErrors();

    expect($status->refresh()->name)->toBe('Renamed');
});

test('default statuses cannot be turned off or deleted, and another can become the default', function () {
    $default = CustomStatuses::defaultFor(TicketStatus::Pending);

    $this->actingAs($this->admin)->put(route('admin.ticket-statuses.update', $default), [
        'name' => null,
        'color' => 'sky',
        'is_active' => false,
    ])->assertSessionHasErrors('is_active');

    $this->actingAs($this->admin)->delete(route('admin.ticket-statuses.destroy', $default));
    expect($default->fresh())->not->toBeNull();

    $other = CustomStatus::factory()->category(TicketStatus::Pending)->inactive()->create();
    $this->actingAs($this->admin)->post(route('admin.ticket-statuses.make-default', $other));

    expect($other->refresh()->is_default)->toBeTrue()
        ->and($other->is_active)->toBeTrue()
        ->and($default->refresh()->is_default)->toBeFalse();
});

test('deleting a status moves its tickets to the default and turns off workflows that set it', function () {
    $status = CustomStatus::factory()->category(TicketStatus::Pending)->create();
    $ticket = Ticket::factory()->create(['ticket_status_id' => $status->id]);
    $workflow = Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [
        ['update_ticket', ['ticket_status_id' => (string) $status->id]],
    ])->create();

    $this->actingAs($this->admin)->delete(route('admin.ticket-statuses.destroy', $status))->assertSessionHasNoErrors();

    expect($status->fresh())->toBeNull()
        ->and($ticket->refresh()->ticket_status_id)->toBe(CustomStatuses::defaultFor(TicketStatus::Pending)->id)
        ->and($ticket->status)->toBe(TicketStatus::Pending)
        ->and($workflow->refresh()->is_active)->toBeFalse();
});

test('agents cannot manage statuses', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get(route('admin.ticket-statuses.index'))
        ->assertForbidden();
});
