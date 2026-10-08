<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Support\UserSessions;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

/**
 * Stops someone from signing in, calling the API or writing in, without deleting them or
 * their history. API tokens are kept (and refused while deactivated); sessions and connected
 * apps end.
 */
class DeactivateUser
{
    /**
     * @throws ValidationException
     */
    public function handle(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['user' => __('You cannot deactivate your own account.')]);
        }

        if ($user->isDeactivated()) {
            return;
        }

        if ($user->isAdmin() && User::administrators()->count() <= 1) {
            throw ValidationException::withMessages(['user' => __('The help desk needs at least one administrator.')]);
        }

        $user->forceFill(['deactivated_at' => now(), 'is_available' => false])->save();

        UserSessions::forget($user);

        $tokens = Token::query()->where('user_id', $user->id)->pluck('id');
        Token::query()->whereKey($tokens)->update(['revoked' => true]);
        RefreshToken::query()->whereIn('access_token_id', $tokens)->update(['revoked' => true]);

        activity()->performedOn($user)->causedBy($actor)->event('deactivated')->log('deactivated');
    }
}
