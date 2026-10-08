<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Support\RichText;
use App\Domain\Tickets\Actions\AddMessage;
use App\Domain\Tickets\Enums\TicketChannel;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\Placeholders;
use App\Domain\Workflows\Engine\RunContext;
use Illuminate\Validation\Rule;

/**
 * Writes on the ticket: an internal note (`add_note`) or a public reply to the requester
 * (`reply`, which is emailed like any agent reply and may set the status afterwards).
 */
class AddMessageNode extends TicketActionNode
{
    public function __construct(private bool $isInternal, private AddMessage $addMessage, private Placeholders $placeholders) {}

    public function type(): string
    {
        return $this->isInternal ? 'add_note' : 'reply';
    }

    protected function actionRules(): array
    {
        return [
            'body' => ['required', 'string', 'max:20000'],
            'status_after' => ['nullable', Rule::enum(TicketStatus::class)],
        ];
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        $ticket = $this->target($data, $context);
        $body = RichText::sanitize($this->placeholders->html((string) $data['body'], $context, $ticket));
        $status = $this->isInternal ? null : TicketStatus::tryFrom((string) ($data['status_after'] ?? ''));

        if (! $context->simulating) {
            $this->addMessage->handle(
                $ticket,
                null,
                $body,
                isInternal: $this->isInternal,
                channel: TicketChannel::Agent,
                statusAfter: $status,
                metadata: $this->authorship($context),
            );
        }

        return NodeResult::next(array_filter([
            'ticket' => $ticket->reference(),
            'excerpt' => RichText::excerpt($body, 200),
            'status' => $status?->value,
        ]));
    }
}
