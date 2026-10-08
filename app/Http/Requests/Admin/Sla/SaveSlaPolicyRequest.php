<?php

namespace App\Http\Requests\Admin\Sla;

use App\Domain\Tickets\Enums\TicketPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSlaPolicyRequest extends FormRequest
{
    /**
     * SLA metrics that can be targeted, in the order they are shown.
     */
    public const METRICS = ['first_response', 'next_reply', 'resolution'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'business_schedule_id' => ['nullable', 'integer', Rule::exists('business_schedules', 'id')],
            'is_active' => ['sometimes', 'boolean'],
            'conditions' => ['nullable', 'array'],
            'conditions.priorities' => ['nullable', 'array'],
            'conditions.priorities.*' => [Rule::enum(TicketPriority::class)],
            'conditions.group_ids' => ['nullable', 'array'],
            'conditions.group_ids.*' => ['integer', Rule::exists('groups', 'id')],
            'conditions.organization_ids' => ['nullable', 'array'],
            'conditions.organization_ids.*' => ['integer', Rule::exists('organizations', 'id')],
            'targets' => ['required', 'array'],
        ];

        foreach (TicketPriority::cases() as $priority) {
            foreach (self::METRICS as $metric) {
                $rules["targets.{$priority->value}.{$metric}"] = ['nullable', 'integer', 'min:1', 'max:525600'];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (TicketPriority::cases() as $priority) {
            foreach (self::METRICS as $metric) {
                $attributes["targets.{$priority->value}.{$metric}"] = __(':priority :metric target', [
                    'priority' => $priority->label(),
                    'metric' => match ($metric) {
                        'first_response' => __('first response'),
                        'next_reply' => __('next reply'),
                        default => __('resolution'),
                    },
                ]);
            }
        }

        return $attributes;
    }

    /**
     * Normalized conditions (empty lists dropped) ready to store.
     *
     * @return array{priorities?: list<string>, group_ids?: list<int>, organization_ids?: list<int>}|null
     */
    public function conditions(): ?array
    {
        $conditions = [];
        $input = (array) $this->validated('conditions', []);

        foreach (['priorities', 'group_ids', 'organization_ids'] as $key) {
            $values = array_values(array_unique((array) ($input[$key] ?? [])));

            if ($values === []) {
                continue;
            }

            $conditions[$key] = $key === 'priorities'
                ? array_map(strval(...), $values)
                : array_map(intval(...), $values);
        }

        return $conditions === [] ? null : $conditions;
    }

    /**
     * Targets in minutes per priority and metric; blank means "not tracked" (null).
     *
     * @return array<string, array{first_response: int|null, next_reply: int|null, resolution: int|null}>
     */
    public function targets(): array
    {
        $input = (array) $this->validated('targets', []);
        $targets = [];

        foreach (TicketPriority::cases() as $priority) {
            $row = (array) ($input[$priority->value] ?? []);
            $targets[$priority->value] = [
                'first_response' => isset($row['first_response']) ? (int) $row['first_response'] : null,
                'next_reply' => isset($row['next_reply']) ? (int) $row['next_reply'] : null,
                'resolution' => isset($row['resolution']) ? (int) $row['resolution'] : null,
            ];
        }

        return $targets;
    }
}
