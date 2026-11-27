<?php

use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Support\Extensions\ModuleRegistry;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;
use Tests\Fixtures\Jira\Jira;

beforeEach(function () {
    Jira::connect();
    $this->agent = User::factory()->agent()->create();
    $this->ticket = Ticket::factory()->create(['subject' => 'Export is broken']);
});

test('agents link an issue by key or Jira link, and the ticket appears on the issue', function (string $typed) {
    Http::fake([
        Jira::API.'/issue/ENG-12?*' => Http::response(Jira::issue()),
        Jira::API.'/issue/ENG-12/remotelink' => Http::response(['id' => 1], 201),
    ]);

    $this->actingAs($this->agent)
        ->postJson(route('agent.tickets.jira.link', $this->ticket), ['issue' => $typed])
        ->assertOk()
        ->assertJsonPath('issues.0.key', 'ENG-12')
        ->assertJsonPath('issues.0.status', 'To Do')
        ->assertJsonPath('issues.0.assignee', 'Dana Dev');

    $link = JiraIssueLink::query()->sole();
    expect($link->ticket_id)->toBe($this->ticket->id)
        ->and($link->issue_id)->toBe('10012')
        ->and($link->url)->toBe(Jira::SITE.'/browse/ENG-12');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/issue/ENG-12/remotelink')
        && $request['globalId'] === 'kitedesk:localhost:ticket:'.$this->ticket->id
        && $request['object']['url'] === route('agent.tickets.show', $this->ticket)
        && $request['object']['status']['resolved'] === false);

    expect(Activity::query()->where('event', 'jira_linked')->sole()->properties['jira'])->toBe('ENG-12');
})->with([
    'key' => 'eng-12',
    'browse link' => Jira::SITE.'/browse/ENG-12',
    'board link' => Jira::SITE.'/jira/software/projects/ENG/boards/1?selectedIssue=ENG-12',
]);

test('linking explains what went wrong when Jira has no such issue', function () {
    Http::fake([Jira::API.'/*' => Http::response(['errorMessages' => ['Issue does not exist or you do not have permission to see it.']], 404)]);

    $this->actingAs($this->agent)
        ->postJson(route('agent.tickets.jira.link', $this->ticket), ['issue' => 'ENG-404'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('jira');

    $this->actingAs($this->agent)
        ->postJson(route('agent.tickets.jira.link', $this->ticket), ['issue' => 'not a key'])
        ->assertJsonValidationErrors('issue');

    expect(JiraIssueLink::query()->count())->toBe(0);
});

test('creating an issue sends the first customer message as ADF and links it', function () {
    $this->ticket->messages()->create(['author_id' => $this->ticket->requester_id, 'body' => '<p>It fails with <strong>large</strong> files.</p>', 'is_internal' => false, 'channel' => 'portal']);

    Http::fake([
        Jira::API.'/issue/createmeta/ENG/issuetypes/10001?*' => Http::response(['fields' => [
            ['fieldId' => 'summary', 'name' => 'Summary', 'required' => true, 'hasDefaultValue' => false],
            ['fieldId' => 'reporter', 'name' => 'Reporter', 'required' => true, 'hasDefaultValue' => true],
        ]]),
        Jira::API.'/issue' => Http::response(['id' => '10012', 'key' => 'ENG-12'], 201),
        Jira::API.'/issue/ENG-12?*' => Http::response(Jira::issue()),
        Jira::API.'/issue/ENG-12/remotelink' => Http::response(['id' => 1], 201),
    ]);

    $this->actingAs($this->agent)
        ->postJson(route('agent.tickets.jira.store', $this->ticket), ['project' => 'ENG', 'issue_type' => '10001', 'summary' => 'Export is broken'])
        ->assertOk()
        ->assertJsonPath('issues.0.key', 'ENG-12');

    Http::assertSent(function (Request $request) {
        if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/rest/api/3/issue')) {
            return false;
        }

        $description = $request['fields']['description'];

        return $request['fields']['project'] === ['key' => 'ENG']
            && $request['fields']['issuetype'] === ['id' => '10001']
            && $description['type'] === 'doc'
            && $description['content'][0]['content'][1] === ['type' => 'text', 'text' => 'large', 'marks' => [['type' => 'strong']]]
            && $description['content'][1]['content'][0]['marks'][0]['attrs']['href'] === route('agent.tickets.show', $this->ticket);
    });
});

test('issue types that need more fields are refused with their names', function () {
    Http::fake([
        Jira::API.'/issue/createmeta/ENG/issuetypes/10001?*' => Http::response(['fields' => [
            ['fieldId' => 'customfield_10020', 'name' => 'Sprint', 'required' => true, 'hasDefaultValue' => false],
        ]]),
    ]);

    $this->actingAs($this->agent)
        ->postJson(route('agent.tickets.jira.store', $this->ticket), ['project' => 'ENG', 'issue_type' => '10001', 'summary' => 'Export is broken'])
        ->assertJsonValidationErrors(['jira' => 'Sprint']);

    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
});

test('unlinking removes the ticket from the issue', function () {
    $link = JiraIssueLink::factory()->for($this->ticket)->create(['issue_key' => 'ENG-12']);
    Http::fake([Jira::API.'/issue/ENG-12/remotelink?*' => Http::response(null, 204)]);

    $this->actingAs($this->agent)
        ->deleteJson(route('agent.tickets.jira.destroy', ['ticket' => $this->ticket, 'jiraIssueLink' => $link]))
        ->assertOk()
        ->assertJsonCount(0, 'issues');

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'globalId='.rawurlencode('kitedesk:localhost:ticket:'.$this->ticket->id)));
    expect(JiraIssueLink::query()->count())->toBe(0);
});

test('a link can only be removed through its own ticket', function () {
    $link = JiraIssueLink::factory()->create();

    $this->actingAs($this->agent)
        ->deleteJson(route('agent.tickets.jira.destroy', ['ticket' => $this->ticket, 'jiraIssueLink' => $link]))
        ->assertNotFound();
});

test('staff without the permission see linked issues but cannot change them', function () {
    $viewer = User::factory()->withPermissions([])->create();
    JiraIssueLink::factory()->for($this->ticket)->create();

    $this->actingAs($viewer)
        ->getJson(route('agent.tickets.jira.index', $this->ticket))
        ->assertOk()
        ->assertJsonPath('can_manage', false)
        ->assertJsonCount(1, 'issues');

    $this->actingAs($viewer)
        ->postJson(route('agent.tickets.jira.link', $this->ticket), ['issue' => 'ENG-1'])
        ->assertForbidden();
});

test('the panel and its actions are gone while the module is off', function () {
    app(ModuleRegistry::class)->disable('jira');

    $this->actingAs($this->agent)
        ->getJson(route('agent.tickets.jira.index', $this->ticket))
        ->assertNotFound();
});
