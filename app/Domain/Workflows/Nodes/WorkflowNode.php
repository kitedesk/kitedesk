<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\RunContext;

/**
 * A step of a workflow graph. Each type validates its own settings and decides which output
 * the run continues from.
 */
interface WorkflowNode
{
    /**
     * The type stored in the graph (`if`, `update_ticket`...).
     */
    public function type(): string;

    /**
     * The outputs (edge handles) the node offers for the given settings.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public function outputs(array $data): array;

    /**
     * Validation rules for the node's settings.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * Run the node. In a simulation (`$context->simulating`) nodes must not change anything.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, RunContext $context): NodeResult;
}
