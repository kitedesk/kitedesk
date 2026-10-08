<?php

namespace App\Http\Requests\Admin;

use App\Domain\Tickets\Enums\RoutingOperator;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Models\TicketField;
use App\Domain\Tickets\Routing\TicketRouter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveRoutingRuleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $fields = [
            ...TicketRouter::FIELDS,
            ...TicketField::query()->pluck('key')->map(fn (string $key): string => "custom_fields.{$key}")->all(),
        ];

        return [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'match' => ['required', Rule::in(['all', 'any'])],
            'conditions' => ['present', 'array', 'max:20'],
            'conditions.*.field' => ['required', 'string', Rule::in($fields)],
            'conditions.*.operator' => ['required', Rule::enum(RoutingOperator::class)],
            'conditions.*.value' => ['present', 'nullable'],
            'actions.group_id' => ['required', 'integer', Rule::exists('groups', 'id')],
            'actions.priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'actions.tags' => ['sometimes', 'array', 'max:20'],
            'actions.tags.*' => ['string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'actions.group_id' => __('group'),
            'conditions.*.field' => __('condition'),
        ];
    }

    /**
     * @return array{name: string, is_active: bool, match: string, conditions: list<array{field: string, operator: string, value: string}>, actions: array{group_id: int, priority: string|null, tags: list<string>}}
     */
    public function ruleAttributes(): array
    {
        /** @var list<array{field: string, operator: string, value: mixed}> $conditions */
        $conditions = $this->validated('conditions', []);
        /** @var list<string> $tags */
        $tags = $this->validated('actions.tags', []);

        return [
            'name' => $this->string('name')->toString(),
            'is_active' => $this->boolean('is_active', true),
            'match' => $this->string('match')->toString(),
            'conditions' => array_map(fn (array $condition): array => [
                'field' => $condition['field'],
                'operator' => $condition['operator'],
                'value' => $this->normalizeValue($condition['value']),
            ], $conditions),
            'actions' => [
                'group_id' => $this->integer('actions.group_id'),
                'priority' => $this->validated('actions.priority'),
                'tags' => array_values(array_unique(array_map(fn (string $tag): string => Str::of($tag)->trim()->lower()->replaceMatches('/\s+/', '_')->toString(), $tags))),
            ],
        ];
    }

    private function normalizeValue(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => trim((string) $value),
            default => '',
        };
    }
}
