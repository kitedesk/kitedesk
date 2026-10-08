<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Support\RichText;
use App\Domain\Tickets\Support\GuestAccess;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\Placeholders;
use App\Domain\Workflows\Engine\RunContext;
use App\Mail\WorkflowEmail;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Sends a standalone email about the ticket (without the conversation) to the requester,
 * the assignee, the ticket's group or any address.
 */
class SendEmailNode extends TicketActionNode
{
    public function __construct(private Placeholders $placeholders) {}

    public function type(): string
    {
        return 'send_email';
    }

    protected function actionRules(): array
    {
        return [
            'to' => ['required', Rule::in(['requester', 'assignee', 'group', 'address'])],
            'address' => ['required_if:to,address', 'nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ];
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        $ticket = $this->target($data, $context);
        $sent = [];

        foreach ($this->recipients($data, $context) as $recipient) {
            $user = $recipient instanceof User ? $recipient : null;
            $subject = trim($this->placeholders->text((string) $data['subject'], $context, $ticket, $user));
            $body = RichText::sanitize($this->placeholders->html((string) $data['body'], $context, $ticket, $user));
            $address = $user !== null ? $user->email : (string) $recipient;
            $url = match (true) {
                $user === null => null,
                $user->isStaff() => route('agent.tickets.show', $ticket),
                default => GuestAccess::urlFor($ticket, $user),
            };

            if (! $context->simulating) {
                Mail::to($address)->queue(new WorkflowEmail($ticket, $subject, $body, $url, $user?->isStaff() ?? false));
            }

            $sent[] = $address;
        }

        return NodeResult::next(['to' => $sent]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, User|string>
     */
    private function recipients(array $data, RunContext $context): Collection
    {
        $ticket = $this->target($data, $context);

        return match ($data['to']) {
            'requester' => collect([$ticket->requester]),
            'assignee' => collect([$ticket->assignee])->filter(),
            'group' => $ticket->group !== null ? $ticket->group->agents()->get()->values() : collect(),
            default => collect(preg_split('/[,;\s]+/', $this->placeholders->text((string) $data['address'], $context, $ticket)) ?: [])
                ->map(fn (string $address): string => mb_strtolower(trim($address)))
                ->filter(fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false)
                ->unique()
                ->take(10)
                ->values(),
        };
    }
}
