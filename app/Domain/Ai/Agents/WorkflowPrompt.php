<?php

namespace App\Domain\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * The "Ask AI" workflow step: an admin-written prompt whose answer is saved to a variable.
 */
#[MaxTokens(1200)]
#[Temperature(0.2)]
#[Timeout(60)]
class WorkflowPrompt implements Agent
{
    use Promptable, UsesKiteDeskProvider;

    public function instructions(): string
    {
        return $this->houseRules()."\n\n".<<<'TEXT'
            You run as one step of an automated helpdesk workflow, and later steps use your answer as is.
            Answer exactly what is asked, with nothing before or after it. When asked for a single value, reply with just that value.
            TEXT;
    }
}
