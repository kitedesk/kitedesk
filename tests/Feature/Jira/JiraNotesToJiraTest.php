<?php

use App\Domain\Jira\Enums\CommentDirection;
use App\Domain\Jira\Enums\StatusCategory;
use App\Domain\Jira\Jobs\PostCommentToJira;
use App\Domain\Jira\Models\JiraCommentLink;
use App\Domain\Jira\Models\JiraIssueLink;
use App\Domain\Jira\Notifications\JiraNeedsAttention;
use App\Domain\Support\Extensions\ModuleRegistry;
use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\Jira\Jira;

beforeEach(function () {
    Jira::connect();
    $this->agent = User::factory()->agent()->create(['name' => 'Ana Agent']);
    $this->ticket = Ticket::factory()->create();
    $this->link = JiraIssueLink::factory()->for($this->ticket)->create(['issue_key' => 'ENG-12']);
});

test('internal notes are posted to linked issues as comments, marked as from KiteDesk', function () {
    Http::fake([Jira::API.'/issue/ENG-12/comment' => Http::response(['id' => '700'], 201)]);

    $note = app(AddMessage::class)->handle($this->ticket, $this->agent, '<p>Customer is on <strong>v2.2</strong>.</p>', isInternal: true);

    Http::assertSent(function (Request $request) {
        $content = $request['body']['content'];

        return str_ends_with($request->url(), '/issue/ENG-12/comment')
            && $content[0]['content'][0]['text'] === 'From KiteDesk ticket '.$this->ticket->reference().' · Ana Agent:'
            && $content[1]['content'][1] === ['type' => 'text', 'text' => 'v2.2', 'marks' => [['type' => 'strong']]];
    });

    expect(JiraCommentLink::query()->sole())
        ->jira_comment_id->toBe('700')
        ->direction->toBe(CommentDirection::Outbound)
        ->ticket_message_id->toBe($note->id)
        ->and($note->refresh()->metadata['jira_sent'])->toBe(['ENG-12']);
});

test('replies to the customer, notes from Jira and issues deleted in Jira are left out', function () {
    Queue::fake();
    $deleted = JiraIssueLink::factory()->for($this->ticket)->create(['deleted_at_jira' => now()]);

    app(AddMessage::class)->handle($this->ticket, $this->agent, '<p>We are on it.</p>');
    app(AddMessage::class)->handle($this->ticket, null, '<p>Dana commented on ENG-12</p>', isInternal: true, metadata: ['jira' => ['issue_key' => 'ENG-12', 'kind' => 'comment']]);
    app(AddMessage::class)->handle($this->ticket, $this->agent, '<p>Note</p>', isInternal: true);

    Queue::assertPushed(PostCommentToJira::class, 1);
    Queue::assertPushed(PostCommentToJira::class, fn (PostCommentToJira $job) => $job->link->is($this->link) && ! $job->link->is($deleted));
});

test('a busy Jira puts the comment back on the queue for as long as it asks', function () {
    Http::fake([Jira::API.'/issue/ENG-12/comment' => Http::response(null, 429, ['Retry-After' => '42'])]);
    $note = $this->ticket->messages()->create(['author_id' => $this->agent->id, 'body' => '<p>Note</p>', 'is_internal' => true, 'channel' => 'agent']);

    $job = (new PostCommentToJira($this->link, $note))->withFakeQueueInteractions();
    $job->handle();

    $job->assertReleased(42);
    expect(JiraCommentLink::query()->count())->toBe(0);
});

test('nothing is sent while the module is off', function () {
    Queue::fake();
    app(ModuleRegistry::class)->disable('jira');

    app(AddMessage::class)->handle($this->ticket, $this->agent, '<p>Note</p>', isInternal: true);

    Queue::assertNotPushed(PostCommentToJira::class);
});

test('the periodic refresh catches status changes webhooks missed', function () {
    $this->link->update(['issue_id' => '10012', 'last_synced_at' => now()->subHour()]);
    JiraIssueLink::factory()->create(['issue_id' => '10050', 'last_synced_at' => now()]);

    Http::fake([
        Jira::API.'/search/jql' => Http::response(['issues' => [Jira::issue('ENG-12', '10012', 'In Progress', 'indeterminate')], 'isLast' => true]),
    ]);

    $this->artisan('jira:refresh')->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request['jql'] === 'id in (10012)');
    expect($this->link->refresh()->status_category)->toBe(StatusCategory::InProgress)
        ->and($this->ticket->messages()->sole()->body)->toContain('ENG-12 moved from To Do to In Progress');
});

test('a refused token tells the integration admins once and shows on the panel', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    Http::fake([Jira::API.'/*' => Http::response(null, 401)]);

    $this->actingAs($this->agent)->postJson(route('agent.tickets.jira.link', $this->ticket), ['issue' => 'ENG-1'])->assertJsonValidationErrors('jira');
    $this->actingAs($this->agent)->postJson(route('agent.tickets.jira.link', $this->ticket), ['issue' => 'ENG-1'])->assertJsonValidationErrors('jira');

    Notification::assertSentToTimes($admin, JiraNeedsAttention::class, 1);

    $this->actingAs($this->agent)
        ->getJson(route('agent.tickets.jira.index', $this->ticket))
        ->assertJsonPath('error', fn (?string $error) => str_contains((string) $error, 'refused'));
});
