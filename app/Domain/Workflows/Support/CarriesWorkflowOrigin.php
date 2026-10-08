<?php

namespace App\Domain\Workflows\Support;

use App\Domain\Workflows\Engine\WorkflowOrigin;

/**
 * For ticket events: records whether a workflow caused the event, and how deep in a chain of
 * workflows it happened, so queued listeners still know after the request is over.
 */
trait CarriesWorkflowOrigin
{
    /**
     * Workflow runs that led to this event (0 = caused by a person or the system).
     */
    public int $workflowDepth = 0;

    /**
     * The workflow whose action caused this event.
     */
    public ?int $workflowId = null;

    protected function captureWorkflowOrigin(): void
    {
        $origin = app(WorkflowOrigin::class);

        $this->workflowDepth = $origin->depth();
        $this->workflowId = $origin->workflowId();
    }
}
