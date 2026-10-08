<?php

use App\Domain\Tickets\Support\BoardPreferences;
use App\Models\User;

beforeEach(function () {
    $this->agent = User::factory()->agent()->create();
});

test('agents start with the default preferences', function () {
    $preferences = BoardPreferences::for($this->agent);

    expect($preferences->layout)->toBe('list')
        ->and($preferences->groupBy)->toBe('status')
        ->and($preferences->cardFields)->toBe(BoardPreferences::DEFAULT_CARD_FIELDS);
});

test('agents save their board preferences, one change at a time', function () {
    $this->actingAs($this->agent)->patch(route('agent.preferences.board'), ['tickets_layout' => 'board'])->assertSessionHasNoErrors();
    $this->actingAs($this->agent)->patch(route('agent.preferences.board'), [
        'group_by' => 'priority',
        'columns' => ['priority' => ['order' => ['low', 'urgent'], 'hidden' => ['normal']]],
        'card_fields' => ['tags', 'sla'],
    ])->assertSessionHasNoErrors();

    $preferences = BoardPreferences::for($this->agent->refresh());

    expect($preferences->layout)->toBe('board')
        ->and($preferences->groupBy)->toBe('priority')
        ->and($preferences->lanes('priority'))->toBe(['order' => ['low', 'urgent'], 'hidden' => ['normal']])
        ->and($preferences->cardFields)->toBe(['sla', 'tags']);

    $this->actingAs($this->agent)->patch(route('agent.preferences.board'), ['reset' => true]);

    expect(BoardPreferences::for($this->agent->refresh())->groupBy)->toBe('status')
        ->and(BoardPreferences::for($this->agent)->layout)->toBe('board');
});

test('unknown groupings and card fields are rejected', function () {
    $this->actingAs($this->agent)->patch(route('agent.preferences.board'), [
        'group_by' => 'requester',
        'card_fields' => ['password'],
        'columns' => ['requester' => ['order' => ['1']]],
    ])->assertSessionHasErrors(['group_by', 'card_fields.0', 'columns']);
});

test('preferences stay out of the shared user data', function () {
    BoardPreferences::update($this->agent, ['group_by' => 'group']);

    expect($this->agent->refresh()->toArray())->not->toHaveKey('preferences');
});
