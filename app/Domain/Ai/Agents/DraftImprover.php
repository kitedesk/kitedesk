<?php

namespace App\Domain\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * Rewrites the reply an agent is writing: fixes, shortens or changes its tone, keeping what it
 * says. Prompted with the draft and the ticket for context.
 */
#[MaxTokens(1500)]
#[Temperature(0.3)]
#[Timeout(120)]
class DraftImprover implements Agent
{
    use Promptable, UsesKiteDeskProvider;

    public const array ACTIONS = ['improve', 'fix_grammar', 'shorten', 'friendlier', 'formal', 'custom'];

    public function __construct(public string $action, public ?string $instruction = null) {}

    public function instructions(): string
    {
        $task = match ($this->action) {
            'fix_grammar' => 'Fix spelling, grammar and punctuation only. Change nothing else.',
            'shorten' => 'Make it shorter and more direct, keeping every fact, step and promise.',
            'friendlier' => 'Make the tone warmer and more empathetic, without getting longer than needed.',
            'formal' => 'Make the tone more formal and professional.',
            'custom' => 'Rewrite it following this instruction from the agent: '.$this->instruction,
            default => 'Improve clarity, structure and tone so it reads like a great support reply.',
        };

        return $this->houseRules()."\n\n".<<<TEXT
            You edit a draft reply written by a support agent. {$task}
            Keep the draft's language, meaning, facts, links, names and any {{placeholders}} exactly. Don't add new information or promises.
            The ticket conversation is given only as context: don't answer it yourself.
            Reply with the rewritten draft only, without any commentary.
            TEXT;
    }
}
