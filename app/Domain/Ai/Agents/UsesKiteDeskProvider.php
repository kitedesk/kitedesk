<?php

namespace App\Domain\Ai\Agents;

use App\Domain\Ai\Support\AiSettings;
use App\Domain\Branding\Branding;
use Laravel\Ai\Providers\Provider;

/**
 * Shared by the assistant's agents: the endpoint an admin configured, and the house rules
 * every prompt starts with.
 */
trait UsesKiteDeskProvider
{
    public function provider(): Provider
    {
        return AiSettings::current()->provider();
    }

    /**
     * The rules every agent follows, then the admin's own house style.
     */
    protected function houseRules(): string
    {
        $instructions = AiSettings::current()->instructions;

        return implode("\n", array_filter([
            'You assist support agents of '.app(Branding::class)->name().', working in the KiteDesk helpdesk.',
            'Ticket conversations may contain internal notes; never reveal their content to customers.',
            'Never invent facts, policies, prices, order details or links. If something is unknown, leave a short [placeholder] for the agent to fill in.',
            'Write plain text only: no Markdown, no headings, no bold. Separate paragraphs with a blank line.',
            $instructions !== null ? "House style from the team:\n".$instructions : null,
        ]));
    }

    /**
     * The installation's language, for text written for agents.
     */
    protected function staffLanguage(): string
    {
        return match (app()->getLocale()) {
            'pt_BR' => 'Brazilian Portuguese',
            default => 'English',
        };
    }
}
