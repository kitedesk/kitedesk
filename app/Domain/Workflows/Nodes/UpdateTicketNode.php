<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\Placeholders;
use App\Domain\Workflows\Engine\RunContext;
use App\Models\User;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Sets ticket properties. Empty settings are left alone; `assignee_id: "none"` unassigns, and
 * `ticket_status_id` (a custom status) wins over `status` (a category).
 */
class UpdateTicketNode extends TicketActionNode
{
    public function __construct(private UpdateTicket $updateTicket, private Placeholders $placeholders) {}

    public function type(): string
    {
        return 'update_ticket';
    }

    protected function actionRules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'ticket_status_id' => ['nullable', 'integer', Rule::exists('ticket_statuses', 'id')->where('is_active', true)],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'type' => ['nullable', Rule::enum(TicketType::class)],
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')],
            'assignee_id' => ['nullable', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== 'none' && ! (is_numeric($value) && User::query()->whereKey((int) $value)->staff()->exists())) {
                    $fail(__('Choose an agent.'));
                }
            }],
            'category_id' => ['nullable', 'integer', Rule::exists('ticket_categories', 'id')],
            'custom_fields' => ['sometimes', 'nullable', 'array', 'max:30'],
            'custom_fields.*' => ['nullable', 'scalar', 'max:1000'],
        ];
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        $ticket = $this->target($data, $context);
        $attributes = $this->attributes($data, $context);

        if ($attributes === []) {
            return NodeResult::next(['changes' => []]);
        }

        if (! $context->simulating) {
            $this->updateTicket->handle($ticket, $attributes);
        }

        return NodeResult::next(['ticket' => $ticket->reference(), 'changes' => $attributes]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, RunContext $context): array
    {
        $attributes = [];

        foreach (['status', 'priority', 'type'] as $key) {
            if (filled($data[$key] ?? null)) {
                $attributes[$key] = $data[$key];
            }
        }

        foreach (['ticket_status_id', 'group_id', 'category_id'] as $key) {
            if (filled($data[$key] ?? null)) {
                $attributes[$key] = (int) $data[$key];
            }
        }

        // A chosen status already sets its category.
        if (array_key_exists('ticket_status_id', $attributes)) {
            unset($attributes['status']);
        }

        if (filled($data['assignee_id'] ?? null)) {
            $attributes['assignee_id'] = $data['assignee_id'] === 'none' ? null : (int) $data['assignee_id'];
        }

        $fields = array_filter(is_array($data['custom_fields'] ?? null) ? $data['custom_fields'] : [], fn (mixed $value): bool => $value !== null && $value !== '');

        if ($fields !== []) {
            $attributes['custom_fields'] = array_map(
                fn (mixed $value): mixed => is_string($value) ? $this->placeholders->text($value, $context) : $value,
                $fields,
            );
        }

        return $attributes;
    }
}
