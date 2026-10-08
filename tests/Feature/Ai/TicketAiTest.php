<?php

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Accounts\Enums\TicketAccess;
use App\Domain\Ai\Agents\DraftImprover;
use App\Domain\Ai\Agents\ReplyDrafter;
use App\Domain\Ai\Agents\TicketSummarizer;
use App\Domain\Ai\Support\AiSettings;
use App\Domain\KnowledgeBase\Models\Article;
use App\Domain\Secrets\Models\Secret;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use App\Models\User;
use Database\Factories\SecretFactory;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    (new AiSettings(enabled: true, baseUrl: 'https://ai.example.test/v1', model: 'test-model'))->save();
    $this->agent = User::factory()->agent()->create();
    $this->ticket = Ticket::factory()->create(['subject' => 'Cannot log in']);
    TicketMessage::factory()->for($this->ticket)->create(['author_id' => $this->ticket->requester_id, 'body' => '<p>My password reset email never arrives.</p>']);
});

test('only agents with the permission, on tickets they can see, can use the assistant', function () {
    TicketSummarizer::fake(['- Summary']);
    $summary = route('agent.tickets.ai.summary', $this->ticket);

    $this->actingAs($this->agent)->getJson($summary)->assertOk();
    $this->actingAs(User::factory()->withPermissions([Permission::ReplyToTickets])->create())->getJson($summary)->assertForbidden();
    $this->actingAs(User::factory()->withPermissions([Permission::UseAi], TicketAccess::Assigned)->create())->getJson($summary)->assertForbidden();

    (new AiSettings(enabled: false, baseUrl: 'https://ai.example.test/v1', model: 'test-model'))->save();
    $this->actingAs($this->agent)->getJson($summary)->assertForbidden();
});

test('a summary is cached until the next message, and reads internal notes but never secrets', function () {
    TicketSummarizer::fake(['- First summary', '- Second summary']);
    TicketMessage::factory()->internal()->for($this->ticket)->create(['author_id' => $this->agent->id, 'body' => '<p>Mail logs show bounces.</p>']);
    Secret::factory()->for($this->ticket)->create();
    $summary = route('agent.tickets.ai.summary', $this->ticket);

    $this->actingAs($this->agent)->getJson($summary)->assertJsonPath('summary', '- First summary');
    $this->actingAs($this->agent)->getJson($summary)->assertJsonPath('summary', '- First summary');
    TicketSummarizer::assertPromptedTimes(1);
    TicketSummarizer::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('[Internal note by '.$this->agent->name)
        && $prompt->contains('Mail logs show bounces.')
        && ! $prompt->contains(SecretFactory::CONTENT));

    TicketMessage::factory()->for($this->ticket)->create(['author_id' => $this->ticket->requester_id]);

    $this->actingAs($this->agent)->getJson($summary)->assertJsonPath('summary', '- Second summary');
});

test('a draft streams back and only uses published articles the agent picked', function () {
    ReplyDrafter::fake(['Hi! Try the steps in our guide.']);
    $published = Article::factory()->create(['title' => 'Resetting your password']);
    $draft = Article::factory()->draft()->create(['title' => 'Unreleased SSO guide']);

    $response = $this->actingAs($this->agent)
        ->postJson(route('agent.tickets.ai.draft', $this->ticket), ['mode' => 'public', 'article_ids' => [$published->id, $draft->id]])
        ->assertOk();

    $text = collect(explode("\n\n", trim($response->streamedContent())))
        ->map(fn (string $event): array => json_decode(substr($event, 6), true))
        ->where('type', 'text')->pluck('text')->join('');

    expect($text)->toBe('Hi! Try the steps in our guide.')
        ->and($response->streamedContent())->toEndWith("data: {\"type\":\"done\"}\n\n");

    ReplyDrafter::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Resetting your password')
        && $prompt->contains(route('help.articles.show', $published))
        && ! $prompt->contains('Unreleased SSO guide')
        && $prompt->contains('My password reset email never arrives.'));
});

test('a provider failure ends the stream with a message the agent can read', function () {
    ReplyDrafter::fake(fn () => throw new RuntimeException('upstream 500'));

    $content = $this->actingAs($this->agent)
        ->postJson(route('agent.tickets.ai.draft', $this->ticket), ['mode' => 'public'])
        ->streamedContent();

    expect($content)->toContain('"type":"error"')->not->toContain('upstream 500');
});

test('improving needs a draft, and an instruction for custom rewrites', function () {
    DraftImprover::fake(['Thanks for your patience!']);
    $improve = route('agent.tickets.ai.improve', $this->ticket);

    $this->actingAs($this->agent)->postJson($improve, ['draft' => '<p></p>', 'action' => 'improve'])->assertJsonValidationErrors('draft');
    $this->actingAs($this->agent)->postJson($improve, ['draft' => '<p>thx</p>', 'action' => 'custom'])->assertJsonValidationErrors('instruction');

    $this->actingAs($this->agent)->postJson($improve, ['draft' => '<p>thx for waiting</p>', 'action' => 'friendlier'])->assertOk();

    DraftImprover::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('thx for waiting'));
});

test('replies sent with an AI draft are marked for staff', function () {
    $this->actingAs($this->agent)
        ->post(route('agent.tickets.messages.store', $this->ticket), ['body' => '<p>Hi there</p>', 'ai_assisted' => true])
        ->assertRedirect();

    expect($this->ticket->messages()->latest('id')->first()->metadata)->toBe(['ai_assisted' => true]);
});
