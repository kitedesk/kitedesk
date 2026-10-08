<?php

namespace App\Domain\Workflows\Engine;

use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Unwinds the graph walk when a node pauses, stops or leaves a loop early.
 */
final class FlowSignal extends RuntimeException
{
    private function __construct(
        public readonly string $kind,
        public readonly ?string $nodeId = null,
        public readonly ?CarbonImmutable $until = null,
        public readonly ?string $waitFor = null,
    ) {
        parent::__construct($kind);
    }

    public static function from(NodeResult $result, string $nodeId): self
    {
        return new self((string) $result->control, $nodeId, $result->waitUntil, $result->waitFor);
    }
}
