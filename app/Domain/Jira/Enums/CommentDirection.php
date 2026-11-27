<?php

namespace App\Domain\Jira\Enums;

enum CommentDirection: string
{
    /** A Jira comment copied to the ticket as an internal note. */
    case Inbound = 'inbound';

    /** An internal note posted to the issue as a comment. */
    case Outbound = 'outbound';
}
