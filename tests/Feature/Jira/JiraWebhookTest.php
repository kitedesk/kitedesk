<?php

use App\Domain\Jira\Enums\StatusCategory;
use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Support\Extensions\ModuleRegistry;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Domain\Tickets\Support\CustomStatuses;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\Jira\Jira;

/**
 * @param  array<string, mixed>  $payload
 */
function deliver(array $payload, ?string $identifier = null, string $secret = Jira::SECRET): TestResponse
{
    [$body, $headers] = Jira::delivery($payload, $identifier, $secret);

    return test()->call('POST', route('jira.webhook'), [], [], [], collect($headers)->mapWithKeys(fn (string $value, string $key): array => ['HTTP_'.strtoupper(str_replace('-', '_', $key)) => $value])->all(), $body);
}

/**
 * @return array<string, mixed>
 */
function issueMoved(string $status, string $category, string $from = 'To Do', string $key = 'ENG-12', string $id = '10012'): array
{
    return [
        'webhookEvent' => 'jira:issue_updated',
        'issue' => Jira::issue($key, $id, $status, $category, $category === 'done' ? 'Fixed' : null),
        'changelog' => ['items' => [['field' => 'status', 'fromString' => $from, 'toString' => $status]]],
    ];
}

beforeEach(function () {
    $this->solved = CustomStatuses::defaultFor(TicketStatus::Solved);
    $this->open = CustomStatuses::defaultFor(TicketStatus::Open);

    Jira::connect([
        StatusCategory::InProgress->value => $this->open->id,
        StatusCategory::Done->value => $this->solved->id,
    ]);

    // Solving or reopening the ticket updates its entry on the issue.
    Http::fake([Jira::API.'/issue/*/remotelink' => Http::response(['id' => 1], 201)]);

    $this->ticket = Ticket::factory()->status(TicketStatus::Pending)->create();
    $this->link = JiraIssueLink::factory()->for($this->ticket)->create(['issue_id' => '10012', 'issue_key' => 'ENG-12']);
});

test('deliveries need the webhook secret\'s signature', function () {
    deliver(issueMoved('Done', 'done'), secret: 'wrong')->assertUnauthorized();

    expect($this->link->refresh()->status_name)->toBe('To Do')
        ->and(TicketMessage::query()->count())->toBe(0);
});

test('a status change adds a note and moves the ticket as mapped', function () {
    deliver(issueMoved('Done', 'done'))->assertAccepted();

    $link = $this->link->refresh();
    expect($link->status_name)->toBe('Done')
        ->and($link->status_category)->toBe(StatusCategory::Done)
        ->and($link->resolution)->toBe('Fixed');

    $note = TicketMessage::query()->sole();
    expect($note->is_internal)->toBeTrue()
        ->and($note->author_id)->toBeNull()
        ->and($note->body)->toContain('ENG-12 moved from To Do to Done')
        ->and($note->metadata['jira']['issue_key'])->toBe('ENG-12');

    expect($this->ticket->refresh()->ticket_status_id)->toBe($this->solved->id);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/issue/ENG-12/remotelink')
        && $request['object']['status']['resolved'] === true);
});

test('done only applies once every linked issue is done', function () {
    JiraIssueLink::factory()->for($this->ticket)->inCategory(StatusCategory::InProgress)->create(['issue_id' => '10099', 'issue_key' => 'OPS-3']);

    deliver(issueMoved('Done', 'done'))->assertAccepted();
    expect($this->ticket->refresh()->status)->toBe(TicketStatus::Pending);

    deliver(issueMoved('Done', 'done', from: 'In Progress', key: 'OPS-3', id: '10099'))->assertAccepted();
    expect($this->ticket->refresh()->ticket_status_id)->toBe($this->solved->id);
});

test('unmapped categories and closed tickets keep their status', function () {
    deliver(issueMoved('Backlog', 'new', from: 'Selected'))->assertAccepted();
    expect($this->ticket->refresh()->status)->toBe(TicketStatus::Pending);

    $this->ticket->update(['status' => TicketStatus::Closed]);
    deliver(issueMoved('In Progress', 'indeterminate', from: 'Backlog'))->assertAccepted();

    expect($this->ticket->refresh()->status)->toBe(TicketStatus::Closed)
        ->and(TicketMessage::query()->count())->toBe(2);
});

test('a repeated delivery is processed once', function () {
    deliver(issueMoved('In Progress', 'indeterminate'), identifier: 'delivery-1')->assertAccepted();
    deliver(issueMoved('In Progress', 'indeterminate'), identifier: 'delivery-1')->assertOk()->assertJsonPath('status', 'duplicate');

    expect(TicketMessage::query()->count())->toBe(1);
});

test('updates that leave the status alone only refresh the issue', function () {
    $payload = ['webhookEvent' => 'jira:issue_updated', 'issue' => Jira::issue()];
    $payload['issue']['fields']['summary'] = 'Export fails for files over 2 GB';

    deliver($payload)->assertAccepted();

    expect($this->link->refresh()->summary)->toBe('Export fails for files over 2 GB')
        ->and(TicketMessage::query()->count())->toBe(0);
});

test('a deleted issue is marked and noted', function () {
    deliver(['webhookEvent' => 'jira:issue_deleted', 'issue' => Jira::issue()])->assertAccepted();

    expect($this->link->refresh()->deleted_at_jira)->not->toBeNull()
        ->and(TicketMessage::query()->sole()->body)->toContain('ENG-12 was deleted in Jira');
});

test('Jira comments become internal notes, once', function () {
    $comment = [
        'webhookEvent' => 'comment_created',
        'issue' => ['id' => '10012', 'key' => 'ENG-12'],
        'comment' => [
            'id' => '500',
            'author' => ['displayName' => 'Dana Dev'],
            'body' => ['type' => 'doc', 'version' => 1, 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Fixed in ', 'marks' => []], ['type' => 'text', 'text' => 'v2.3', 'marks' => [['type' => 'code']]]]]]],
        ],
    ];

    deliver($comment)->assertAccepted();
    deliver($comment)->assertAccepted();

    $note = TicketMessage::query()->sole();
    expect($note->is_internal)->toBeTrue()
        ->and($note->body)->toContain('Dana Dev commented on ENG-12 in Jira')
        ->and($note->body)->toContain('Fixed in <code>v2.3</code>')
        ->and($note->metadata['jira']['kind'])->toBe('comment');
});

test('comments KiteDesk posted are not copied back', function () {
    deliver([
        'webhookEvent' => 'comment_created',
        'issue' => ['id' => '10012', 'key' => 'ENG-12'],
        'comment' => ['id' => '501', 'author' => ['displayName' => 'KiteDesk bot'], 'body' => 'From KiteDesk ticket #1 · Ana: please check'],
    ])->assertAccepted();

    expect(TicketMessage::query()->count())->toBe(0);
});

test('the webhook is gone while the module is off', function () {
    app(ModuleRegistry::class)->disable('jira');

    deliver(issueMoved('Done', 'done'))->assertNotFound();
});

test('comments by the Jira account KiteDesk uses, or in wiki markup with its marker, are not copied back', function () {
    Http::fake([Jira::API.'/myself' => Http::response(['accountId' => 'kitedesk-bot', 'displayName' => 'KiteDesk Bot'])]);

    deliver([
        'webhookEvent' => 'comment_created',
        'issue' => ['id' => '10012', 'key' => 'ENG-12'],
        'comment' => ['id' => '510', 'author' => ['accountId' => 'kitedesk-bot', 'displayName' => 'KiteDesk Bot'], 'body' => 'Customer is on v2.2'],
    ])->assertAccepted();

    deliver([
        'webhookEvent' => 'comment_created',
        'issue' => ['id' => '10012', 'key' => 'ENG-12'],
        'comment' => ['id' => '511', 'author' => ['accountId' => 'someone-else'], 'body' => '*[From KiteDesk ticket #1 · Ana:|https://kd.test/agent/tickets/1]*'."\nCustomer is on v2.2"],
    ])->assertAccepted();

    expect(TicketMessage::query()->count())->toBe(0);
});

test('comments in wiki markup are converted', function () {
    Http::fake([Jira::API.'/myself' => Http::response(['accountId' => 'kitedesk-bot'])]);

    deliver([
        'webhookEvent' => 'comment_created',
        'issue' => ['id' => '10012', 'key' => 'ENG-12'],
        'comment' => ['id' => '520', 'author' => ['accountId' => 'dana', 'displayName' => 'Dana Dev'], 'body' => "Fixed in *v2.3*, see [notes|https://docs.test/2.3]\n\n* export\n* import"],
    ])->assertAccepted();

    expect(TicketMessage::query()->sole()->body)
        ->toContain('<strong>v2.3</strong>')
        ->toContain('<a href="https://docs.test/2.3"')
        ->toContain('<li>export</li>');
});
