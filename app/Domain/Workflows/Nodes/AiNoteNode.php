<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Ai\Agents\ReplyDrafter;
use App\Domain\Ai\Agents\TicketSummarizer;
use App\Domain\Ai\Support\AiSettings;
use App\Domain\Ai\Support\RelatedArticles;
use App\Domain\Ai\Support\TicketContext;
use App\Domain\Support\RichText;
use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\RunContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Adds an internal note written by the AI assistant: a summary of the ticket, or a suggested
 * reply (using related help center articles) for the agent to review. Nothing reaches the
 * customer. Continues from "failed" when the assistant is off or the provider fails; test runs
 * don't call the model.
 */
class AiNoteNode extends TicketActionNode
{
    /**
     * @param  'summary'|'draft'  $kind
     */
    public function __construct(private string $kind, private AddMessage $addMessage) {}

    public function type(): string
    {
        return $this->kind === 'summary' ? 'ai_summary_note' : 'ai_draft_note';
    }

    public function outputs(array $data): array
    {
        return ['out', 'failed'];
    }

    protected function actionRules(): array
    {
        return $this->kind === 'draft'
            ? ['instruction' => ['nullable', 'string', 'max:500'], 'use_articles' => ['sometimes', 'boolean']]
            : [];
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        $ticket = $this->target($data, $context);
        $settings = AiSettings::current();

        if (! $settings->isAvailable()) {
            return NodeResult::branch('failed', ['error' => __('The AI assistant is not set up.')]);
        }

        if ($context->simulating) {
            return NodeResult::next(['ticket' => $ticket->reference(), 'note' => $this->kind === 'summary' ? __('AI summary') : __('Suggested reply')]);
        }

        try {
            $prompt = TicketContext::for($ticket, $settings->contextMessages);

            if ($this->kind === 'summary') {
                $text = (new TicketSummarizer)->prompt($prompt)->text;
            } else {
                $articles = (bool) ($data['use_articles'] ?? true) ? RelatedArticles::for($ticket, 3) : collect();
                $instruction = trim((string) ($data['instruction'] ?? ''));

                $text = (new ReplyDrafter($ticket->assignee->name ?? __('the agent')))->prompt(implode("\n\n", array_filter([
                    $prompt,
                    $articles->isNotEmpty() ? "Help center articles:\n\n".RelatedArticles::asPrompt($articles) : null,
                    $instruction !== '' ? 'Instruction: '.$instruction : null,
                ])))->text;
            }
        } catch (Throwable $exception) {
            Log::warning('AI note failed in a workflow.', ['ticket' => $ticket->id, 'workflow' => $context->workflowId, 'exception' => $exception]);

            return NodeResult::branch('failed', ['error' => Str::limit($exception->getMessage(), 200)]);
        }

        $heading = $this->kind === 'summary'
            ? __('AI summary')
            : __('Suggested reply (AI draft, not sent to the customer)');

        $this->addMessage->handle(
            $ticket,
            null,
            '<p><strong>'.e($heading).'</strong></p>'.RichText::fromPlainText(trim($text)),
            isInternal: true,
            channel: TicketChannel::Agent,
            metadata: [...$this->authorship($context), 'ai_assisted' => true],
        );

        return NodeResult::next(['ticket' => $ticket->reference(), 'excerpt' => Str::limit(trim($text), 200)]);
    }
}
