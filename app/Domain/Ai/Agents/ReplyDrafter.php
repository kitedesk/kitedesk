<?php

namespace App\Domain\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * Drafts the next reply (or internal note) on a ticket, optionally grounded in help center
 * articles. The agent reviews it before anything is sent.
 */
#[MaxTokens(1500)]
#[Temperature(0.4)]
#[Timeout(120)]
class ReplyDrafter implements Agent
{
    use Promptable, UsesKiteDeskProvider;

    public function __construct(public string $agentName, public bool $internal = false) {}

    public function instructions(): string
    {
        $task = $this->internal
            ? "Write an internal note for the other agents on this ticket, in {$this->staffLanguage()}: what's going on and what should happen next. Customers never see it."
            : 'Write the next reply to the customer, signed by the agent "'.$this->agentName.'". Answer in the language the customer writes in. Be warm, clear and concise; address what is still open in their latest message, and say what happens next.';

        return $this->houseRules()."\n\n".$task."\n".<<<'TEXT'
            When help center articles are provided and relevant, use them as the source of truth and include their URL so the customer can read more. Ignore articles that don't fit.
            Reply with the message text only, without a subject line or any commentary.
            TEXT;
    }
}
