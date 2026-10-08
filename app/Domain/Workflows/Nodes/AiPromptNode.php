<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Ai\Agents\WorkflowPrompt;
use App\Domain\Ai\Support\AiSettings;
use App\Domain\Ai\Support\TicketContext;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\Placeholders;
use App\Domain\Workflows\Engine\RunContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Ask AI": sends the admin's prompt (with placeholders, and optionally the ticket conversation)
 * and saves the answer as `{{vars.<save_as>}}`. Continues from "failed" when the assistant is off
 * or the provider fails. Test runs ask the model too: nothing changes on the ticket.
 */
class AiPromptNode extends TicketActionNode
{
    private const int MAX_SAVED_CHARACTERS = 10_000;

    public function __construct(private Placeholders $placeholders) {}

    public function type(): string
    {
        return 'ai_prompt';
    }

    public function outputs(array $data): array
    {
        return ['out', 'failed'];
    }

    protected function actionRules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:5000'],
            'include_conversation' => ['sometimes', 'boolean'],
            'save_as' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/i'],
        ];
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        $ticket = $this->target($data, $context);
        $prompt = trim($this->placeholders->text((string) $data['prompt'], $context));

        if (! AiSettings::current()->isAvailable()) {
            return NodeResult::branch('failed', ['error' => __('The AI assistant is not set up.')]);
        }

        if ((bool) ($data['include_conversation'] ?? true)) {
            $prompt = TicketContext::for($ticket, AiSettings::current()->contextMessages)."\n\n---\n\n".$prompt;
        }

        try {
            $answer = trim((new WorkflowPrompt)->prompt($prompt)->text);
        } catch (Throwable $exception) {
            Log::warning('AI prompt failed in a workflow.', ['ticket' => $ticket->id, 'workflow' => $context->workflowId, 'exception' => $exception]);

            return NodeResult::branch('failed', ['error' => Str::limit($exception->getMessage(), 200)]);
        }

        $context->vars[(string) $data['save_as']] = Str::limit($answer, self::MAX_SAVED_CHARACTERS);

        return NodeResult::next(['name' => $data['save_as'], 'answer' => Str::limit($answer, 500)]);
    }
}
