<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\RunContext;
use App\Domain\Workflows\Enums\IdleAnchor;
use App\Domain\Workflows\Enums\WorkflowTrigger;
use Illuminate\Validation\Rule;

/**
 * Where every workflow starts. Its settings say which event starts the workflow:
 *
 * - `event`: a {@see WorkflowTrigger} value
 * - `fields`: "Ticket updated" only fires when one of these changed (empty = any change)
 * - `channels`: limit "Ticket created" and reply triggers to channels (empty = any)
 * - `anchor`, `amount`, `unit`, `statuses`: time-based ("no customer reply for 3 days")
 * - `run_by`: manual workflows, `agents` or `admins`
 */
class TriggerNode extends Node
{
    /**
     * Ticket changes "Ticket updated" can wait for.
     */
    public const array WATCHED_FIELDS = ['subject', 'status', 'ticket_status_id', 'priority', 'type', 'assignee_id', 'group_id', 'category_id', 'tags', 'collaborators', 'custom_fields'];

    public function type(): string
    {
        return 'trigger';
    }

    public function rules(): array
    {
        return [
            'event' => ['required', Rule::enum(WorkflowTrigger::class)],
            'fields' => ['sometimes', 'array'],
            'fields.*' => [Rule::in(self::WATCHED_FIELDS)],
            'channels' => ['sometimes', 'array'],
            'channels.*' => [Rule::enum(TicketChannel::class)],
            'anchor' => ['required_if:event,ticket_idle', 'nullable', Rule::enum(IdleAnchor::class)],
            'amount' => ['required_if:event,ticket_idle', 'nullable', 'integer', 'min:1', 'max:1000'],
            'unit' => ['required_if:event,ticket_idle', 'nullable', Rule::in(['hours', 'days'])],
            'statuses' => ['sometimes', 'array'],
            'statuses.*' => [Rule::enum(TicketStatus::class)],
            'run_by' => ['nullable', Rule::in(['agents', 'admins'])],
        ];
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        return NodeResult::next(array_filter([
            'event' => $context->trigger['event'] ?? null,
            'changed' => $context->changedFields() ?: null,
        ]));
    }
}
