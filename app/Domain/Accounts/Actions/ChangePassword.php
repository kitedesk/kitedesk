<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Support\PasswordPolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Every new password goes through here: the old one is remembered so it can't be reused, the
 * expiry clock restarts, and the change is logged. Without an actor the person reset it with an
 * emailed link; an actor other than the person is an admin setting it for them.
 */
class ChangePassword
{
    public function handle(User $user, string $password, ?User $actor = null, bool $requireChange = false): void
    {
        DB::transaction(function () use ($user, $password, $requireChange): void {
            $user->passwordHistories()->create(['password' => $user->password]);

            $user->passwordHistories()
                ->whereNotIn('id', $user->passwordHistories()->latest('id')->limit(PasswordPolicy::MAX_REMEMBERED)->pluck('id'))
                ->delete();

            $user->forceFill([
                'password' => $password,
                'password_changed_at' => now(),
                'password_change_required' => $requireChange,
            ])->save();
        });

        $event = match (true) {
            $actor === null => 'password_reset',
            $actor->is($user) => 'password_changed',
            default => 'password_set',
        };

        activity()
            ->useLog($event === 'password_set' ? 'users' : 'auth')
            ->performedOn($user)
            ->causedBy($actor ?? $user)
            ->withProperties($requireChange ? ['change_required' => true] : [])
            ->event($event)
            ->log($event);
    }
}
