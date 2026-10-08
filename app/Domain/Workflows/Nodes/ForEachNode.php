<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Support\TicketFilters;
use App\Domain\Workflows\Engine\LoopCollections;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\RunContext;
use Illuminate\Validation\Rule;

/**
 * Runs the path connected to `each` once per item of a list, then continues from `done`.
 *
 * The loop body is everything reachable from `each`; when the body's path ends, the engine
 * moves to the next item. Inside the body, `{{item.*}}`, `{{loop.index}}` and `{{loop.count}}`
 * describe the current item.
 */
class ForEachNode extends Node
{
    public function __construct(private LoopCollections $collections) {}

    public function type(): string
    {
        return 'for_each';
    }

    public function outputs(array $data): array
    {
        return ['each', 'done'];
    }

    public function rules(): array
    {
        return [
            'collection' => ['required', Rule::in(LoopCollections::COLLECTIONS)],
            'statuses' => ['sometimes', 'array'],
            'statuses.*' => [Rule::enum(TicketStatus::class)],
            'filters' => ['required_if:collection,find_tickets', 'nullable', 'array'],
            'filters.*' => ['nullable', 'scalar', 'max:255'],
            'variable' => ['required_if:collection,variable', 'nullable', 'string', 'regex:/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)*$/i'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.LoopCollections::MAX_ITEMS],
        ];
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        $items = $this->collections->items($data, $context);

        return NodeResult::loop($items, ['count' => count($items)]);
    }

    /**
     * Filter keys "Find tickets" accepts (the ticket queue's filters).
     *
     * @return list<string>
     */
    public static function filterKeys(): array
    {
        return TicketFilters::KEYS;
    }
}
