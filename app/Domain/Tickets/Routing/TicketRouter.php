<?php

namespace App\Domain\Tickets\Routing;

use App\Domain\Tickets\Enums\RoutingOperator;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Models\RoutingRule;
use App\Domain\Tickets\Models\Ticket;
use BackedEnum;
use Illuminate\Support\Str;

/**
 * Evaluates routing rules against a ticket and applies the first match.
 */
class TicketRouter
{
    /**
     * Ticket properties a condition can test, besides `custom_fields.<key>`.
     */
    public const array FIELDS = ['category', 'priority', 'type', 'channel', 'organization', 'requester_email'];

    /**
     * Apply the first matching rule to the (possibly unsaved) ticket. Tags are returned for the
     * caller to attach once the ticket exists.
     *
     * @param  bool  $setPriority  False when the priority was chosen explicitly and must be kept.
     * @return list<string> Tags to add.
     */
    public function route(Ticket $ticket, bool $setPriority = true): array
    {
        $rule = $this->firstMatch($ticket);

        if ($rule === null) {
            return [];
        }

        $ticket->group_id = $rule->actions['group_id'];

        $priority = TicketPriority::tryFrom((string) ($rule->actions['priority'] ?? ''));

        if ($setPriority && $priority !== null) {
            $ticket->priority = $priority;
        }

        return $rule->actions['tags'] ?? [];
    }

    public function firstMatch(Ticket $ticket): ?RoutingRule
    {
        return RoutingRule::query()
            ->active()
            ->ordered()
            ->get()
            ->first(fn (RoutingRule $rule): bool => $this->matches($rule, $ticket));
    }

    public function matches(RoutingRule $rule, Ticket $ticket): bool
    {
        if ($rule->conditions === []) {
            return true;
        }

        $results = array_map(fn (array $condition): bool => $this->conditionHolds($condition, $ticket), $rule->conditions);

        return $rule->match === 'any' ? in_array(true, $results, true) : ! in_array(false, $results, true);
    }

    /**
     * @param  array{field: string, operator: string, value: mixed}  $condition
     */
    private function conditionHolds(array $condition, Ticket $ticket): bool
    {
        $operator = RoutingOperator::tryFrom($condition['operator']);

        if ($operator === null) {
            return false;
        }

        if ($condition['field'] === 'category') {
            $within = $ticket->category !== null && $ticket->category->isWithin((int) $condition['value']);

            return $operator === RoutingOperator::IsNot ? ! $within : $within;
        }

        $actual = $this->normalize($this->valueOf($condition['field'], $ticket));
        $expected = $this->normalize($condition['value']);

        return match ($operator) {
            RoutingOperator::Is => $actual === $expected,
            RoutingOperator::IsNot => $actual !== $expected,
            RoutingOperator::Contains => $expected !== '' && Str::contains($actual, $expected, ignoreCase: true),
        };
    }

    private function valueOf(string $field, Ticket $ticket): mixed
    {
        if (str_starts_with($field, 'custom_fields.')) {
            return ($ticket->custom_fields ?? [])[Str::after($field, 'custom_fields.')] ?? null;
        }

        return match ($field) {
            'priority' => $ticket->priority,
            'type' => $ticket->type,
            'channel' => $ticket->channel,
            'organization' => $ticket->organization_id,
            'requester_email' => $ticket->requester->email,
            default => null,
        };
    }

    private function normalize(mixed $value): string
    {
        return match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => mb_strtolower(trim((string) $value)),
            default => '',
        };
    }
}
