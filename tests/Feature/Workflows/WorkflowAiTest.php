<?php

use App\Domain\Ai\Agents\ReplyDrafter;
use App\Domain\Ai\Agents\TicketClassifier;
use App\Domain\Ai\Agents\TicketSummarizer;
use App\Domain\Ai\Agents\WorkflowPrompt;
use App\Domain\Ai\Support\AiSettings;
use App\Domain\KnowledgeBase\Models\Article;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketCategory;
use App\Domain\Workflows\Enums\WorkflowTrigger;
use App\Domain\Workflows\Models\Workflow;
use App\Models\User;
use Database\Factories\WorkflowFactory;
use Laravel\Ai\Prompts\AgentPrompt;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    (new AiSettings(enabled: true, baseUrl: 'https://ai.example.test/v1', model: 'test-model'))->save();
    $this->billing = TicketCategory::factory()->create(['name' => 'Billing']);
    $this->refunds = TicketCategory::factory()->childOf($this->billing)->create(['name' => 'Refunds']);
    TicketCategory::factory()->create(['name' => 'General']);
});

/**
 * A ticket-created workflow: classify, then tag the branch taken and the intent found.
 *
 * @param  array<string, mixed>  $settings
 */
function classifyWorkflow(array $settings = []): Workflow
{
    return Workflow::factory()->flow(WorkflowTrigger::TicketCreated, [
        'classify' => ['ai_classify', [
            'fields' => [
                'category' => ['enabled' => true, 'apply' => true],
                'priority' => ['enabled' => true, 'apply' => true],
                'sentiment' => ['enabled' => true],
                'intent' => ['enabled' => true, 'options' => ['refund', 'bug', 'sales']],
            ],
            'keep_existing' => true,
            'min_confidence' => 70,
            'save_as' => 'ai',
            ...$settings,
        ]],
        'confident' => ['add_tags', ['tags' => ['ai_{{vars.ai.intent}}']]],
        'unsure' => ['add_tags', ['tags' => ['needs_triage']]],
        'failed' => ['add_tags', ['tags' => ['ai_failed']]],
    ], [
        ['trigger', 'out', 'classify'],
        ['classify', 'confident', 'confident'],
        ['classify', 'unsure', 'unsure'],
        ['classify', 'failed', 'failed'],
    ])->create();
}

/**
 * @return array<string, array{value: mixed, confidence: float}|string>
 */
function classification(float $priorityConfidence = 0.9, string $category = 'Billing › Refunds'): array
{
    return [
        'category' => ['value' => $category, 'confidence' => 0.92],
        'priority' => ['value' => 'high', 'confidence' => $priorityConfidence],
        'sentiment' => ['value' => 'angry', 'confidence' => 0.95],
        'intent' => ['value' => 'refund', 'confidence' => 0.88],
        'reason' => 'Charged twice and wants the money back.',
    ];
}

test('a confident classification is applied and saved for later steps', function () {
    TicketClassifier::fake([classification()]);
    classifyWorkflow();

    $ticket = workflowTicket(['subject' => 'Charged twice!', 'body' => '<p>I want my money back.</p>'])->refresh();

    expect($ticket->category_id)->toBe($this->refunds->id)
        ->and($ticket->priority)->toBe(TicketPriority::High)
        ->and($ticket->tags()->pluck('name')->all())->toContain('ai_refund');

    TicketClassifier::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('I want my money back.')
        && $prompt->agent instanceof TicketClassifier
        && $prompt->agent->fields['category']['choices'] === ['Billing › Refunds', 'General']);
});

test('only confident answers are applied; the rest send the run to unsure', function () {
    TicketClassifier::fake([classification(priorityConfidence: 0.4), classification(category: 'Shipping')]);
    classifyWorkflow();

    $lowPriorityConfidence = workflowTicket()->refresh();
    $unknownCategory = workflowTicket()->refresh();

    expect($lowPriorityConfidence->tags()->pluck('name')->all())->toBe(['needs_triage'])
        ->and($lowPriorityConfidence->priority)->not->toBe(TicketPriority::High)
        ->and($lowPriorityConfidence->category_id)->toBe($this->refunds->id)
        ->and($unknownCategory->tags()->pluck('name')->all())->toBe(['needs_triage'])
        ->and($unknownCategory->category_id)->toBeNull()
        ->and($unknownCategory->priority)->toBe(TicketPriority::High);
});

test('a category the customer chose is kept', function () {
    TicketClassifier::fake([classification()]);
    classifyWorkflow();
    $general = TicketCategory::query()->where('name', 'General')->sole();

    $ticket = workflowTicket(['category_id' => $general->id])->refresh();

    expect($ticket->category_id)->toBe($general->id)
        ->and($ticket->priority)->toBe(TicketPriority::High);
});

test('the run continues from failed when the provider errors or AI is off', function (bool $configured) {
    TicketClassifier::fake(fn () => throw new RuntimeException('upstream down'));
    (new AiSettings(enabled: $configured, baseUrl: 'https://ai.example.test/v1', model: 'test-model'))->save();
    classifyWorkflow();

    expect(workflowTicket()->tags()->pluck('name')->all())->toBe(['ai_failed']);
})->with(['provider error' => true, 'AI off' => false]);

test('ask AI saves the answer for later steps', function () {
    WorkflowPrompt::fake(['A-1042']);
    Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [
        ['ai_prompt', ['prompt' => 'Extract the order number for {{requester.first_name}}.', 'include_conversation' => true, 'save_as' => 'order']],
        ['add_note', ['body' => '<p>Order {{vars.order}}</p>']],
    ])->create();

    $ticket = workflowTicket(['body' => '<p>Order A-1042 never arrived</p>'], User::factory()->create(['name' => 'Ana Lima']));

    expect($ticket->messages()->where('is_internal', true)->sole()->body)->toContain('Order A-1042');
    WorkflowPrompt::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Extract the order number for Ana.')
        && $prompt->contains('Order A-1042 never arrived'));
});

test('AI notes add a summary or a suggested reply as internal notes, never public replies', function () {
    TicketSummarizer::fake(['- Wants a refund']);
    ReplyDrafter::fake(['Hi! Here is how refunds work.']);
    Article::factory()->create(['title' => 'Refund policy']);
    Workflow::factory()->chain(WorkflowTrigger::TicketCreated, [
        ['ai_summary_note', []],
        ['ai_draft_note', ['use_articles' => true, 'instruction' => 'Be brief.']],
    ])->create();

    $notes = workflowTicket(['subject' => 'Refund policy question'])->messages()->where('is_internal', true)->oldest('id')->get();

    expect($notes)->toHaveCount(2)
        ->and($notes[0]->body)->toContain('Wants a refund')
        ->and($notes[1]->body)->toContain('Here is how refunds work.')
        ->and($notes[1]->metadata['ai_assisted'])->toBeTrue();
    ReplyDrafter::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Refund policy') && $prompt->contains('Be brief.'));
});

test('a test run asks the AI but leaves the ticket alone', function () {
    TicketClassifier::fake([classification()]);
    $ticket = Ticket::factory()->create();
    $graph = WorkflowFactory::graph(WorkflowTrigger::TicketCreated, [
        'classify' => ['ai_classify', ['fields' => ['category' => ['enabled' => true, 'apply' => true]], 'min_confidence' => 70, 'save_as' => 'ai']],
    ], [['trigger', 'out', 'classify']]);

    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('admin.workflows.test'), ['graph' => $graph, 'ticket_id' => $ticket->id])
        ->assertOk()
        ->assertJsonPath('steps.1.handle', 'confident')
        ->assertJsonPath('steps.1.output.results.category.value', 'Billing › Refunds');

    expect($ticket->refresh()->category_id)->toBeNull();
});
