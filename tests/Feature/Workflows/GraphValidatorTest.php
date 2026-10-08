<?php

use App\Domain\Workflows\Engine\GraphValidator;
use App\Domain\Workflows\Enums\WorkflowTrigger;
use Database\Factories\WorkflowFactory;

require_once __DIR__.'/helpers.php';

/**
 * @param  array<string, array{0: string, 1?: array<string, mixed>}>  $nodes
 * @param  list<array{0: string, 1: string, 2: string}>  $edges
 * @return array<string, string>
 */
function graphErrors(array $nodes, array $edges): array
{
    return app(GraphValidator::class)->validate(WorkflowFactory::graph(WorkflowTrigger::TicketCreated, $nodes, $edges));
}

test('a valid graph has no errors', function () {
    expect(graphErrors([
        'each' => ['for_each', ['collection' => 'linked_tickets']],
        'check' => ['if', ['conditions' => workflowCondition('item.status', 'is', 'pending')]],
        'solve' => ['update_ticket', ['apply_to' => 'item', 'status' => 'solved']],
        'set' => ['set_variable', ['name' => 'total', 'value' => '{{loop.count}}', 'kind' => 'number']],
        'note' => ['add_note', ['body' => '<p>{{vars.total}}</p>']],
    ], [
        ['trigger', 'out', 'each'], ['each', 'each', 'check'], ['check', 'true', 'solve'], ['solve', 'out', 'set'], ['each', 'done', 'note'],
    ]))->toBe([]);
});

test('graph problems are reported on the graph or the node', function (array $nodes, array $edges, string $key, string $message) {
    expect(graphErrors($nodes, $edges))->toHaveKey($key, $message);
})->with([
    'invalid settings' => [['a' => ['update_ticket', ['status' => 'exploded']]], [['trigger', 'out', 'a']], 'nodes.a', 'The selected status is invalid.'],
    'unknown output' => [['a' => ['add_note', ['body' => 'x']]], [['trigger', 'true', 'a']], 'nodes.trigger', 'This step has a connection from an output it doesn\'t have. Reconnect it.'],
    'orphan' => [['a' => ['add_note', ['body' => 'x']]], [], 'nodes.a', 'Connect this step to the workflow, or delete it.'],
    'cycle' => [['a' => ['add_note', ['body' => 'x']]], [['trigger', 'out', 'a'], ['a', 'out', 'trigger']], 'graph', 'Steps cannot loop back to an earlier step. Use "For each" to repeat steps.'],
    'invalid pattern' => [['a' => ['if', ['conditions' => workflowCondition('subject', 'matches', '(unclosed')]]], [['trigger', 'out', 'a']], 'nodes.a', '"(unclosed" is not a valid pattern.'],
    'wait in loop' => [
        ['each' => ['for_each', ['collection' => 'tags']], 'w' => ['wait', ['amount' => 1, 'unit' => 'hours']]],
        [['trigger', 'out', 'each'], ['each', 'each', 'w']], 'nodes.w', 'Waiting is not possible inside a loop.',
    ],
    'item outside loop' => [['a' => ['add_note', ['body' => '{{item.name}}']]], [['trigger', 'out', 'a']], 'nodes.a', 'Loop item values can only be used inside a "For each" loop.'],
    'undefined variable' => [['a' => ['add_note', ['body' => '{{vars.missing}}']]], [['trigger', 'out', 'a']], 'nodes.a', 'The variable "missing" is not set by any step.'],
    'loop body joins the rest' => [
        ['each' => ['for_each', ['collection' => 'tags']], 'a' => ['add_note', ['body' => 'x']]],
        [['trigger', 'out', 'each'], ['each', 'each', 'a'], ['each', 'done', 'a']], 'nodes.each', 'Steps inside the loop cannot also continue after it. End the loop path, or use "done" for what follows.',
    ],
]);

test('a workflow needs exactly one trigger', function () {
    $graph = WorkflowFactory::graph(WorkflowTrigger::TicketCreated, [], []);
    $graph['nodes'][] = [...$graph['nodes'][0], 'id' => 'second'];

    expect(app(GraphValidator::class)->validate($graph))->toBe(['graph' => 'A workflow needs exactly one trigger.']);
});
