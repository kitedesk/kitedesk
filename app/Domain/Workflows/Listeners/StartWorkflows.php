<?php

namespace App\Domain\Workflows\Listeners;

use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Domain\Sla\Events\SlaTargetMissed;
use App\Domain\Tickets\Events\MessageCreated;
use App\Domain\Tickets\Events\TicketCreated;
use App\Domain\Tickets\Events\TicketUpdated;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\RunStatus;
use App\Domain\Workflows\Enums\WorkflowTrigger;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowRun;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Starts the active workflows whose trigger matches a ticket event, and wakes runs waiting
 * for the customer's reply.
 *
 * Loop protection: events caused by workflows carry their depth; past
 * {@see WorkflowEngine::MAX_DEPTH} nothing starts, a workflow never triggers itself, and each
 * workflow runs at most its run limit per ticket.
 */
class StartWorkflows implements ShouldQueue
{
    public function __construct(private WorkflowEngine $engine) {}

    public function handle(TicketCreated|TicketUpdated|MessageCreated|SlaTargetMissed $event): void
    {
        if (! PlanLimits::allows(Feature::Workflows)) {
            return;
        }

        [$trigger, $ticket, $payload] = $this->describe($event);

        if ($trigger === WorkflowTrigger::CustomerReplied) {
            $this->resumeRunsWaitingForReply($ticket);
        }

        $depth = $event instanceof SlaTargetMissed ? 0 : $event->workflowDepth;
        $causedBy = $event instanceof SlaTargetMissed ? null : $event->workflowId;

        if ($depth >= WorkflowEngine::MAX_DEPTH) {
            return;
        }

        $workflows = Workflow::query()->active()->where('trigger', $trigger)->orderBy('id')->get();

        foreach ($workflows as $workflow) {
            if ($workflow->id === $causedBy || ! $this->matches($workflow, $event, $ticket)) {
                continue;
            }

            if ($workflow->runs()->where('ticket_id', $ticket->id)->count() >= $workflow->runLimit()) {
                continue;
            }

            $this->engine->start($workflow, $ticket, ['event' => $trigger->value, ...$payload], $depth);
        }
    }

    /**
     * @return array{WorkflowTrigger, Ticket, array<string, mixed>}
     */
    private function describe(TicketCreated|TicketUpdated|MessageCreated|SlaTargetMissed $event): array
    {
        if ($event instanceof MessageCreated) {
            $message = $event->message;
            $author = $message->author;

            $trigger = match (true) {
                $message->is_internal => WorkflowTrigger::NoteAdded,
                $author === null || $author->isStaff() => WorkflowTrigger::AgentReplied,
                default => WorkflowTrigger::CustomerReplied,
            };

            return [$trigger, $message->ticket, ['message_id' => $message->id]];
        }

        return match (true) {
            $event instanceof TicketCreated => [WorkflowTrigger::TicketCreated, $event->ticket, []],
            $event instanceof TicketUpdated => [WorkflowTrigger::TicketUpdated, $event->ticket, ['changes' => $event->changes]],
            default => [WorkflowTrigger::SlaBreached, $event->ticket, []],
        };
    }

    private function matches(Workflow $workflow, TicketCreated|TicketUpdated|MessageCreated|SlaTargetMissed $event, Ticket $ticket): bool
    {
        $settings = $workflow->triggerSettings();
        $channels = is_array($settings['channels'] ?? null) ? $settings['channels'] : [];
        $fields = is_array($settings['fields'] ?? null) ? $settings['fields'] : [];

        return match (true) {
            $event instanceof TicketCreated => $channels === [] || in_array($ticket->channel->value, $channels, true),
            $event instanceof MessageCreated => $channels === [] || in_array($event->message->channel->value, $channels, true),
            $event instanceof TicketUpdated => $fields === [] || array_intersect($fields, array_keys($event->changes)) !== [],
            default => true,
        };
    }

    private function resumeRunsWaitingForReply(Ticket $ticket): void
    {
        WorkflowRun::query()
            ->where('ticket_id', $ticket->id)
            ->where('status', RunStatus::Waiting)
            ->where('context->waiting_for', 'reply')
            ->get()
            ->each(fn (WorkflowRun $run): bool => $this->engine->resume($run, 'replied'));
    }
}
