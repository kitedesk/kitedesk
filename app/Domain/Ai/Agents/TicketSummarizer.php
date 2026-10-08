<?php

namespace App\Domain\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * A short briefing on a ticket for the agent picking it up. Prompted with TicketContext.
 */
#[MaxTokens(700)]
#[Temperature(0.2)]
#[Timeout(90)]
class TicketSummarizer implements Agent
{
    use Promptable, UsesKiteDeskProvider;

    public function instructions(): string
    {
        return $this->houseRules()."\n\n".<<<TEXT
            Summarize the support ticket you are given for an agent who is about to work on it.
            Write 3 to 6 short lines, each starting with "- ": what the customer needs, what has been tried or promised so far, anything waiting on someone, and the next step.
            Mention internal notes when they matter: this summary is only shown to staff.
            Write in {$this->staffLanguage()}, whatever language the conversation is in.
            TEXT;
    }
}
