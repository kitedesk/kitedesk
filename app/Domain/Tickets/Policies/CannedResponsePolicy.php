<?php

namespace App\Domain\Tickets\Policies;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Tickets\Models\CannedResponse;
use App\Models\User;

class CannedResponsePolicy
{
    /**
     * Agents edit their personal responses; shared ones are managed by roles allowed to share.
     */
    public function update(User $user, CannedResponse $response): bool
    {
        return $response->isPersonal()
            ? $response->user_id === $user->id
            : $user->hasPermission(Permission::ShareCannedResponses);
    }

    public function delete(User $user, CannedResponse $response): bool
    {
        return $this->update($user, $response);
    }
}
