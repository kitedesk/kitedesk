<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Workflows\Engine\RunContext;
use Illuminate\Validation\Rule;

/**
 * An action on a ticket: the one that triggered the run, or (`apply_to: item`) the ticket
 * being looped over.
 */
abstract class TicketActionNode extends Node
{
    /**
     * Settings rules of the action itself.
     *
     * @return array<string, mixed>
     */
    abstract protected function actionRules(): array;

    public function rules(): array
    {
        return [
            'apply_to' => ['nullable', Rule::in(['trigger', 'item'])],
            ...$this->actionRules(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function target(array $data, RunContext $context): Ticket
    {
        return $context->targetTicket(is_string($data['apply_to'] ?? null) ? $data['apply_to'] : null);
    }

    /**
     * Metadata stored on messages the workflow writes, so the thread can say who wrote them.
     *
     * @return array{workflow: array{id: int, name: string}}
     */
    protected function authorship(RunContext $context): array
    {
        return ['workflow' => ['id' => $context->workflowId, 'name' => $context->workflowName]];
    }
}
