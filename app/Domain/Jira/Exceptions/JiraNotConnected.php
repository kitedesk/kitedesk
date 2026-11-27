<?php

namespace App\Domain\Jira\Exceptions;

/**
 * Jira isn't set up, or it refused KiteDesk's credentials (401/403).
 */
class JiraNotConnected extends JiraException {}
