<?php

namespace App\Domain\Accounts\Enums;

/**
 * Whether a person works tickets (staff) or asks for help (customer). What a staff member
 * may do is decided by their role (see `RoleCatalog`).
 */
enum UserType: string
{
    case Staff = 'staff';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Staff => __('Team member'),
            self::Customer => __('Customer'),
        };
    }
}
