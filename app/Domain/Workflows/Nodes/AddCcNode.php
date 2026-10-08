<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\Placeholders;
use App\Domain\Workflows\Engine\RunContext;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Copies people on the ticket. Unknown addresses become customer accounts, as when an agent
 * adds them by hand.
 */
class AddCcNode extends TicketActionNode
{
    public function __construct(private UpdateTicket $updateTicket, private Placeholders $placeholders) {}

    public function type(): string
    {
        return 'add_cc';
    }

    protected function actionRules(): array
    {
        return [
            'emails' => ['required', 'array', 'min:1', 'max:10'],
            'emails.*' => ['required', 'string', 'max:255'],
        ];
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        $ticket = $this->target($data, $context);
        $emails = collect(array_filter((array) ($data['emails'] ?? []), is_string(...)))
            ->map(fn (string $email): string => mb_strtolower(trim($this->placeholders->text($email, $context, $ticket))))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values();

        if (! $context->simulating && $emails->isNotEmpty()) {
            $ids = $emails->map(fn (string $email): int => User::query()->firstOrCreate(
                ['email' => $email],
                ['name' => Str::before($email, '@'), 'password' => Str::random(40)],
            )->id);

            $this->updateTicket->handle($ticket, [
                'collaborator_ids' => array_values(array_unique([...$ticket->collaboratorIds(), ...$ids->all()])),
            ]);
        }

        return NodeResult::next(['ticket' => $ticket->reference(), 'emails' => $emails->all()]);
    }
}
