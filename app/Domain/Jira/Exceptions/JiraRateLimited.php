<?php

namespace App\Domain\Jira\Exceptions;

/**
 * Jira answered 429. Wait `retryAfter` seconds before calling again.
 */
class JiraRateLimited extends JiraException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct(__('Jira is busy. Try again in a moment.'), 429);
    }
}
