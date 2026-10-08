<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\UserType;
use App\Domain\Accounts\Support\RoleCatalog;
use App\Models\User;

/**
 * An administrator who signs in with the password given here, already verified. Used for the
 * first account of an installation (the setup screen and `kitedesk:create-admin`).
 */
class CreateAdministrator
{
    public function handle(string $name, string $email, string $password, ?string $timezone = null): User
    {
        $user = new User([
            'name' => $name,
            'email' => mb_strtolower($email),
            'password' => $password,
            'timezone' => $timezone,
        ]);
        $user->forceFill(['type' => UserType::Staff, 'email_verified_at' => now()])->save();
        $user->syncRoles([RoleCatalog::administrator()]);

        return $user;
    }
}
