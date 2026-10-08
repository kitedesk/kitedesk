<?php

namespace App\Domain\Workflows\Support;

/**
 * Starting points offered when creating a workflow.
 */
class WorkflowTemplates
{
    /**
     * @return list<array{key: string, name: string, description: string}>
     */
    public static function all(): array
    {
        return array_map(fn (string $key): array => [
            'key' => $key,
            'name' => self::get($key)['name'],
            'description' => self::get($key)['description'],
        ], ['blank', 'auto_close_pending', 'escalate_urgent', 'nudge_customer']);
    }

    /**
     * @return array{name: string, description: string, graph: array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}}
     */
    public static function get(string $key): array
    {
        return match ($key) {
            'auto_close_pending' => [
                'name' => __('Solve pending tickets after 3 days'),
                'description' => __('When the customer has not answered for 3 days, leave a note and solve the ticket.'),
                'graph' => self::graph(
                    ['event' => 'ticket_idle', 'anchor' => 'agent_reply', 'amount' => 3, 'unit' => 'days', 'statuses' => ['pending']],
                    [
                        ['note', 'add_note', ['body' => '<p>'.e(__('Solved automatically: no reply from the customer in 3 days.')).'</p>']],
                        ['solve', 'update_ticket', ['status' => 'solved']],
                    ],
                    [['trigger', 'out', 'note'], ['note', 'out', 'solve']],
                ),
            ],
            'escalate_urgent' => [
                'name' => __('Escalate unassigned urgent tickets'),
                'description' => __('Urgent tickets still unassigned after an hour alert the administrators.'),
                'graph' => self::graph(
                    ['event' => 'ticket_created'],
                    [
                        ['urgent', 'if', ['conditions' => ['match' => 'all', 'conditions' => [['field' => 'priority', 'operator' => 'is', 'value' => 'urgent']]]]],
                        ['wait', 'wait', ['amount' => 1, 'unit' => 'hours']],
                        ['unassigned', 'if', ['conditions' => ['match' => 'all', 'conditions' => [['field' => 'assignee', 'operator' => 'is_empty', 'value' => '']]]]],
                        ['alert', 'notify', ['to' => 'admins', 'message' => __('Urgent ticket {{ticket.number}} has been unassigned for an hour.')]],
                    ],
                    [['trigger', 'out', 'urgent'], ['urgent', 'true', 'wait'], ['wait', 'out', 'unassigned'], ['unassigned', 'true', 'alert']],
                ),
            ],
            'nudge_customer' => [
                'name' => __('Remind the customer'),
                'description' => __('When a ticket is set to pending, wait 2 days for an answer, then send a reminder.'),
                'graph' => self::graph(
                    ['event' => 'ticket_updated', 'fields' => ['status']],
                    [
                        ['pending', 'if', ['conditions' => ['match' => 'all', 'conditions' => [['field' => 'status', 'operator' => 'is', 'value' => 'pending']]]]],
                        ['wait', 'wait_for_reply', ['amount' => 2, 'unit' => 'days']],
                        ['remind', 'reply', ['body' => '<p>'.e(__('Hi {{requester.first_name}}, we are still waiting for your answer. Just reply to this message.')).'</p>', 'status_after' => 'pending']],
                    ],
                    [['trigger', 'out', 'pending'], ['pending', 'true', 'wait'], ['wait', 'timeout', 'remind']],
                ),
            ],
            default => [
                'name' => __('Blank workflow'),
                'description' => __('Start from a trigger and add your own steps.'),
                'graph' => self::graph(['event' => 'ticket_created'], [], []),
            ],
        };
    }

    /**
     * Steps laid out top to bottom.
     *
     * @param  array<string, mixed>  $trigger
     * @param  list<array{0: string, 1: string, 2: array<string, mixed>}>  $nodes
     * @param  list<array{0: string, 1: string, 2: string}>  $edges
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    private static function graph(array $trigger, array $nodes, array $edges): array
    {
        $graphNodes = [['id' => 'trigger', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => $trigger]];

        foreach ($nodes as $index => [$id, $type, $data]) {
            $graphNodes[] = ['id' => $id, 'type' => $type, 'position' => ['x' => 0, 'y' => ($index + 1) * 160], 'data' => $data];
        }

        return [
            'nodes' => $graphNodes,
            'edges' => array_map(fn (array $edge): array => [
                'id' => "{$edge[0]}-{$edge[1]}-{$edge[2]}",
                'source' => $edge[0],
                'sourceHandle' => $edge[1],
                'target' => $edge[2],
            ], $edges),
        ];
    }
}
