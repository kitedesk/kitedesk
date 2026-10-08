<?php

namespace App\Domain\Workflows\Nodes;

/**
 * Defaults for nodes with a single output and no settings.
 */
abstract class Node implements WorkflowNode
{
    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public function outputs(array $data): array
    {
        return ['out'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
