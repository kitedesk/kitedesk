<?php

namespace App\Domain\Accounts\Enums;

/**
 * What a customer may do for the organization staff confirmed them in.
 */
enum OrganizationRole: string
{
    /** Works there; their tickets count as the organization's. */
    case Member = 'member';

    /** May also act for the organization: request changes, approve, decide. */
    case AuthorizedContact = 'authorized_contact';

    public function label(): string
    {
        return match ($this) {
            self::Member => __('Member'),
            self::AuthorizedContact => __('Authorized contact'),
        };
    }
}
