<?php

use App\Domain\Accounts\Models\Group;
use App\Domain\Tickets\Models\CannedResponse;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->agent = User::factory()->agent()->create();
});

test('agents see their own, shared and group responses only', function () {
    $billing = Group::factory()->create();
    $billing->agents()->attach($this->agent);

    $own = CannedResponse::factory()->for($this->agent)->create(['title' => 'A own']);
    $shared = CannedResponse::factory()->shared()->create(['title' => 'B shared']);
    $group = CannedResponse::factory()->create(['title' => 'C group', 'user_id' => null, 'group_id' => $billing->id]);
    CannedResponse::factory()->create(['title' => 'D someone else']);
    CannedResponse::factory()->create(['title' => 'E other group', 'user_id' => null, 'group_id' => Group::factory()->create()->id]);

    expect(CannedResponse::query()->availableTo($this->agent)->orderBy('title')->pluck('id')->all())
        ->toBe([$own->id, $shared->id, $group->id]);

    $this->actingAs($this->agent)
        ->get(route('agent.tickets.show', Ticket::factory()->create()))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload->has('cannedResponses', 3)));
});

test('agents keep personal responses; only admins share them', function () {
    $this->actingAs($this->agent)->post(route('agent.canned-responses.store'), [
        'title' => 'Thanks',
        'body' => '<p>Thanks {{requester.first_name}}!</p><script>alert(1)</script>',
    ])->assertSessionHasNoErrors();

    $response = CannedResponse::query()->sole();
    expect($response->body)->toBe('<p>Thanks {{requester.first_name}}!</p>')
        ->and($response->user_id)->toBe($this->agent->id)
        ->and($response->isPersonal())->toBeTrue();

    $this->actingAs($this->agent)
        ->post(route('agent.canned-responses.store'), ['title' => 'All', 'body' => '<p>x</p>', 'is_shared' => true])
        ->assertSessionHasErrors('is_shared');

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('agent.canned-responses.store'), ['title' => 'All', 'body' => '<p>x</p>', 'is_shared' => true])
        ->assertSessionHasNoErrors();
    $sharedResponse = CannedResponse::query()->where('title', 'All')->sole();

    $this->actingAs($this->agent)
        ->put(route('agent.canned-responses.update', $sharedResponse), ['title' => 'Hijack', 'body' => '<p>x</p>'])
        ->assertForbidden();
    $this->actingAs(User::factory()->agent()->create())
        ->delete(route('agent.canned-responses.destroy', $response))
        ->assertForbidden();
    $this->actingAs($this->agent)->delete(route('agent.canned-responses.destroy', $response));

    expect(CannedResponse::query()->pluck('title')->all())->toBe(['All']);
});

test('the management page lists available responses', function () {
    CannedResponse::factory()->for($this->agent)->create();

    $this->actingAs($this->agent)
        ->get(route('agent.canned-responses.index'))
        ->assertInertia(fn (Assert $page) => $page->component('agent/canned-responses/index')->has('responses', 1)->where('responses.0.can_edit', true));
});
