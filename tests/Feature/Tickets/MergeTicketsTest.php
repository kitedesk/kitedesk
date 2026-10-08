<?php

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Tag;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->agent = User::factory()->agent()->create();
    $this->source = Ticket::factory()->create(['subject' => 'Duplicate']);
    $this->target = Ticket::factory()->create();
    TicketMessage::factory()->for($this->source)->create(['body' => '<p>From the duplicate</p>', 'is_internal' => false]);
    TicketMessage::factory()->for($this->target)->create(['body' => '<p>Original</p>', 'is_internal' => false]);
    $this->source->tags()->attach(Tag::idsFor(['vip']));
});

test('merging moves the conversation, copies the requester and closes the duplicate', function () {
    $this->actingAs($this->agent)
        ->post(route('agent.tickets.merge', $this->source), ['target_id' => $this->target->id])
        ->assertRedirect(route('agent.tickets.show', $this->target));

    $this->source->refresh();
    $bodies = $this->target->messages()->oldest('id')->pluck('body')->all();

    expect($this->source->status)->toBe(TicketStatus::Closed)
        ->and($this->source->merged_into_id)->toBe($this->target->id)
        ->and($bodies)->toContain('<p>From the duplicate</p>', '<p>Original</p>')
        ->and($this->target->collaboratorIds())->toBe([$this->source->requester_id])
        ->and($this->target->tags()->pluck('name')->all())->toBe(['vip'])
        ->and($this->target->messages()->where('is_internal', true)->count())->toBe(1)
        ->and($this->source->messages()->where('is_internal', true)->count())->toBe(1);

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.show', $this->source))
        ->assertInertia(fn (Assert $page) => $page->where('mergedInto.id', $this->target->id));
});

test('tickets cannot be merged into themselves, twice, or by light agents', function () {
    $this->actingAs($this->agent)
        ->post(route('agent.tickets.merge', $this->source), ['target_id' => $this->source->id])
        ->assertSessionHasErrors('target_id');

    $this->actingAs(User::factory()->lightAgent()->create())
        ->post(route('agent.tickets.merge', $this->source), ['target_id' => $this->target->id])
        ->assertForbidden();

    $this->actingAs($this->agent)->post(route('agent.tickets.merge', $this->source), ['target_id' => $this->target->id]);
    $this->actingAs($this->agent)
        ->post(route('agent.tickets.merge', $this->source), ['target_id' => Ticket::factory()->create()->id])
        ->assertForbidden();
});

test('related tickets are linked in both directions and can be unlinked', function () {
    $this->actingAs($this->agent)
        ->post(route('agent.tickets.links.store', $this->source), ['linked_id' => $this->target->id])
        ->assertSessionHasNoErrors();

    expect($this->source->linkedTickets()->pluck('tickets.id')->all())->toBe([$this->target->id])
        ->and($this->target->linkedTickets()->pluck('tickets.id')->all())->toBe([$this->source->id]);

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.show', $this->target))
        ->assertInertia(fn (Assert $page) => $page->where('linkedTickets.0.id', $this->source->id));

    $this->actingAs($this->agent)->delete(route('agent.tickets.links.destroy', [$this->target, $this->source]));

    expect($this->source->linkedTickets()->count())->toBe(0);
});

test('a ticket cannot be linked to one the agent may not change', function () {
    $closed = Ticket::factory()->status(TicketStatus::Closed)->create();

    $this->actingAs($this->agent)
        ->post(route('agent.tickets.links.store', $this->source), ['linked_id' => $closed->id])
        ->assertForbidden();

    expect($this->source->linkedTickets()->count())->toBe(0);
});
