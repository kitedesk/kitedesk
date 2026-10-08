<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Tickets\Actions\UpdateTicket;
use App\Domain\Tickets\Routing\AutoAssigner;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\RunContext;

/**
 * Hands an unassigned ticket to an agent of its group, using the group's assignment mode.
 */
class AutoAssignNode extends TicketActionNode
{
    public function __construct(private AutoAssigner $assigner, private UpdateTicket $updateTicket) {}

    public function type(): string
    {
        return 'auto_assign';
    }

    protected function actionRules(): array
    {
        return [];
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        $ticket = $this->target($data, $context);

        if ($context->simulating) {
            return NodeResult::next(['ticket' => $ticket->reference(), 'group_id' => $ticket->group_id]);
        }

        // The assigner changes the model it's given; a copy lets UpdateTicket record the change properly.
        $agent = $this->assigner->assign(clone $ticket);

        if ($agent !== null) {
            $this->updateTicket->handle($ticket, ['assignee_id' => $agent->id]);
        }

        return NodeResult::next(['ticket' => $ticket->reference(), 'assignee' => $agent?->name]);
    }
}
