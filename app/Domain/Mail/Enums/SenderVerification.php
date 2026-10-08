<?php

namespace App\Domain\Mail\Enums;

/**
 * Whether the receiving mail server confirmed the From address (DMARC, or DKIM/SPF aligned with it).
 */
enum SenderVerification: string
{
    /**
     * The sender's domain vouched for the message.
     */
    case Passed = 'passed';

    /**
     * The checks ran and the From address could not be confirmed (likely forged).
     */
    case Failed = 'failed';

    /**
     * The message carries no authentication results to judge by.
     */
    case Unknown = 'unknown';
}
