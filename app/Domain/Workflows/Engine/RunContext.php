<?php

namespace App\Domain\Workflows\Engine;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketMessage;
use RuntimeException;

/**
 * Everything nodes can see while a run executes: the ticket that triggered it, the trigger
 * payload, variables and the loop being iterated.
 */
final class RunContext
{
    /**
     * @var list<array{node_id: string, items: list<mixed>, index: int}>
     */
    private array $loops = [];

    /**
     * @var array<int, Ticket|null>
     */
    private array $ticketCache = [];

    /**
     * @param  array<string, mixed>  $trigger  The trigger payload: `event`, `changes`, `message_id`.
     * @param  array<string, mixed>  $vars
     */
    public function __construct(
        public readonly Ticket $ticket,
        public readonly int $workflowId,
        public readonly string $workflowName,
        public readonly array $trigger = [],
        public array $vars = [],
        public readonly bool $simulating = false,
    ) {}

    /**
     * The ticket an action works on: the trigger ticket, or the ticket being looped over.
     */
    public function targetTicket(?string $applyTo): Ticket
    {
        if ($applyTo !== 'item') {
            return $this->ticket;
        }

        $item = $this->item();
        $id = is_array($item) && ($item['type'] ?? null) === 'ticket' ? (int) $item['id'] : null;
        $ticket = $id !== null ? $this->findTicket($id) : null;

        if ($ticket === null) {
            throw new RuntimeException(__('The current loop item is not a ticket.'));
        }

        return $ticket;
    }

    public function findTicket(int $id): ?Ticket
    {
        if ($id === $this->ticket->id) {
            return $this->ticket;
        }

        return $this->ticketCache[$id] ??= Ticket::query()->find($id);
    }

    /**
     * The message that triggered the run, or else the ticket's latest public message.
     */
    public function message(): ?TicketMessage
    {
        $id = $this->trigger['message_id'] ?? null;

        if ($id !== null) {
            $message = TicketMessage::query()->whereKey($id)->where('ticket_id', $this->ticket->id)->first();

            if ($message !== null) {
                return $message;
            }
        }

        return $this->ticket->messages()->public()->latest('id')->first();
    }

    /**
     * @return list<string>
     */
    public function changedFields(): array
    {
        return array_keys(is_array($this->trigger['changes'] ?? null) ? $this->trigger['changes'] : []);
    }

    /**
     * @param  list<mixed>  $items
     */
    public function enterLoop(string $nodeId, array $items): void
    {
        $this->loops[] = ['node_id' => $nodeId, 'items' => $items, 'index' => 0];
    }

    public function setLoopIndex(int $index): void
    {
        $loop = array_pop($this->loops);

        if ($loop !== null) {
            $this->loops[] = [...$loop, 'index' => $index];
        }
    }

    public function leaveLoop(): void
    {
        array_pop($this->loops);
    }

    public function inLoop(): bool
    {
        return $this->loops !== [];
    }

    /**
     * The current loop item (innermost loop), or null outside loops.
     */
    public function item(): mixed
    {
        $loop = $this->currentLoop();

        return $loop !== null ? $loop['items'][$loop['index']] ?? null : null;
    }

    /**
     * 1-based iteration of the innermost loop, for the run history.
     */
    public function iteration(): ?int
    {
        $loop = $this->currentLoop();

        return $loop !== null ? $loop['index'] + 1 : null;
    }

    public function loopCount(): ?int
    {
        $loop = $this->currentLoop();

        return $loop !== null ? count($loop['items']) : null;
    }

    /**
     * @return array{node_id: string, items: list<mixed>, index: int}|null
     */
    private function currentLoop(): ?array
    {
        return $this->loops === [] ? null : $this->loops[array_key_last($this->loops)];
    }
}
