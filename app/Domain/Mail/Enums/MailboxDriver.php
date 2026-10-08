<?php

namespace App\Domain\Mail\Enums;

/**
 * How a mailbox receives email.
 */
enum MailboxDriver: string
{
    case Imap = 'imap';
    case Postmark = 'postmark';
    case Mailgun = 'mailgun';

    public function label(): string
    {
        return match ($this) {
            self::Imap => __('IMAP mailbox'),
            self::Postmark => __('Postmark inbound webhook'),
            self::Mailgun => __('Mailgun inbound webhook'),
        };
    }
}
