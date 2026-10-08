<?php

namespace App\Domain\Secrets\Policies;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Secrets\Models\Secret;
use App\Models\User;

/**
 * Secrets are only ever shown to signed-in accounts: agents read the answers to requests,
 * and the people a ticket belongs to (requester and those copied) answer requests and read
 * what agents share.
 */
class SecretPolicy
{
    /**
     * Read the content.
     */
    public function view(User $user, Secret $secret): bool
    {
        return $secret->isRequest()
            ? $this->isTicketAgent($user, $secret)
            : $this->isTicketCustomer($user, $secret);
    }

    /**
     * Open the secret's page: the customers it was sent to.
     */
    public function open(User $user, Secret $secret): bool
    {
        return $this->isTicketCustomer($user, $secret);
    }

    /**
     * Answer a request.
     */
    public function submit(User $user, Secret $secret): bool
    {
        return $secret->isRequest() && $this->isTicketCustomer($user, $secret);
    }

    /**
     * Make it unreadable right away.
     */
    public function revoke(User $user, Secret $secret): bool
    {
        return $user->can('useSecrets', $secret->ticket);
    }

    private function isTicketAgent(User $user, Secret $secret): bool
    {
        return $user->isStaff() && $user->hasPermission(Permission::UseSecrets) && $secret->ticket->isVisibleTo($user);
    }

    private function isTicketCustomer(User $user, Secret $secret): bool
    {
        return ! $user->isStaff() && $secret->ticket->involves($user);
    }
}
