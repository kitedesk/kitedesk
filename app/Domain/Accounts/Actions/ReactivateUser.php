<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Entitlements\Enums\Limit;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Lets a deactivated person back in. Team members take a seat again, so the plan must have one.
 */
class ReactivateUser
{
    /**
     * @throws ValidationException
     */
    public function handle(User $user, User $actor): void
    {
        if (! $user->isDeactivated()) {
            return;
        }

        if ($user->isStaff()) {
            PlanLimits::ensureRoomFor(Limit::AgentSeats, 'user');
        }

        $user->forceFill(['deactivated_at' => null, 'is_available' => true])->save();

        activity()->performedOn($user)->causedBy($actor)->event('reactivated')->log('reactivated');
    }
}
