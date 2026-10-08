<?php

namespace App\Domain\Ai\Agents;

use App\Domain\Ai\Support\AiSettings;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Provider;

/**
 * The admin page's "Test connection": a tiny prompt against settings that may not be saved yet.
 */
#[MaxTokens(20)]
#[Timeout(30)]
class ConnectionCheck implements Agent
{
    use Promptable;

    public function __construct(public AiSettings $settings) {}

    public function provider(): Provider
    {
        return $this->settings->provider();
    }

    public function instructions(): string
    {
        return 'You check that a connection works. Reply with the single word OK.';
    }
}
