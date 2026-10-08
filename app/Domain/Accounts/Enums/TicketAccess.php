<?php

namespace App\Domain\Accounts\Enums;

/**
 * Which tickets the members of a role can see in the agent workspace.
 */
enum TicketAccess: string
{
    case All = 'all';
    case Groups = 'groups';
    case Assigned = 'assigned';

    public function label(): string
    {
        return match ($this) {
            self::All => __('All tickets'),
            self::Groups => __('Tickets in their groups'),
            self::Assigned => __('Tickets assigned to them'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::All => __('Every ticket in the help desk.'),
            self::Groups => __('Tickets in the groups they belong to, plus tickets assigned to them or that they are copied on.'),
            self::Assigned => __('Only tickets assigned to them or that they are copied on.'),
        };
    }
}
