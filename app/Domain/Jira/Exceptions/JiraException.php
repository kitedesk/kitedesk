<?php

namespace App\Domain\Jira\Exceptions;

use RuntimeException;

/**
 * Jira refused or failed a request. The message is safe to show to agents.
 */
class JiraException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0)
    {
        parent::__construct($message, $status);
    }
}
