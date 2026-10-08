<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Accounts\Enums\UserType;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\Placeholders;
use App\Domain\Workflows\Engine\RunContext;
use App\Domain\Workflows\Notifications\WorkflowAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Alerts agents in the app and by email: the assignee, the ticket's group, administrators or
 * chosen people.
 */
class NotifyNode extends TicketActionNode
{
    public function __construct(private Placeholders $placeholders) {}

    public function type(): string
    {
        return 'notify';
    }

    protected function actionRules(): array
    {
        return [
            'to' => ['required', Rule::in(['assignee', 'group', 'admins', 'users'])],
            'user_ids' => ['required_if:to,users', 'nullable', 'array', 'max:50'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where('type', UserType::Staff->value)],
            'message' => ['required', 'string', 'max:1000'],
        ];
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        $ticket = $this->target($data, $context);
        $message = trim($this->placeholders->text((string) $data['message'], $context, $ticket));
        $recipients = $this->recipients($data, $context);

        if (! $context->simulating && $recipients->isNotEmpty()) {
            Notification::send($recipients, new WorkflowAlert($ticket, $context->workflowName, $message));
        }

        return NodeResult::next(['to' => $recipients->pluck('name')->all(), 'message' => $message]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, User>
     */
    private function recipients(array $data, RunContext $context): Collection
    {
        $ticket = $this->target($data, $context);
        $staff = User::query()->staff();

        return match ($data['to']) {
            'assignee' => $staff->whereKey($ticket->assignee_id ?? 0)->get(),
            'group' => $ticket->group !== null ? $ticket->group->agents()->get() : new Collection,
            'admins' => User::administrators(),
            default => $staff->whereKey(array_map(intval(...), $data['user_ids'] ?? []))->get(),
        };
    }
}
