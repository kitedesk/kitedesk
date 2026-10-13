<?php

namespace App\Domain\Audit\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;

/**
 * Signing in and out, failed attempts, and changes to how someone signs in, for the audit log.
 * Password changes are recorded where they happen (ChangePassword).
 */
class RecordAuthEvent
{
    /**
     * @var array<class-string, string>
     */
    public const array EVENTS = [
        Login::class => 'login',
        Logout::class => 'logout',
        Failed::class => 'login_failed',
        Lockout::class => 'locked_out',
        TwoFactorAuthenticationFailed::class => 'two_factor_failed',
        TwoFactorAuthenticationConfirmed::class => 'two_factor_enabled',
        TwoFactorAuthenticationDisabled::class => 'two_factor_disabled',
        RecoveryCodesGenerated::class => 'recovery_codes_generated',
        PasskeyRegistered::class => 'passkey_added',
        PasskeyDeleted::class => 'passkey_removed',
    ];

    public function handle(object $event): void
    {
        // Only people signing in to the help desk (not other guards, such as a console's).
        if (property_exists($event, 'guard') && $event->guard !== 'web') {
            return;
        }

        $name = self::EVENTS[$event::class];
        $attempt = $event instanceof Failed || $event instanceof Lockout;
        $email = match (true) {
            $event instanceof Failed => $event->credentials['email'] ?? null,
            $event instanceof Lockout => $event->request->input('email'),
            default => null,
        };
        $email = is_string($email) ? mb_strtolower($email) : null;

        $user = property_exists($event, 'user') ? $event->user : null;

        // Fortify reports failed attempts without the account, so it is looked up by email.
        if ($attempt && ! $user instanceof User && $email !== null) {
            $user = User::query()->where('email', $email)->first();
        }

        if (! $attempt && ! $user instanceof User) {
            return;
        }

        $logger = activity()->useLog('auth')->event($name);

        // A failed attempt is nobody's doing; it is filed under the account it was for, if any.
        if ($attempt) {
            $logger->causedByAnonymous();
        } else {
            $logger->causedBy($user);
        }

        if ($user instanceof User) {
            $logger->performedOn($user);
        }

        $logger->withProperties(match (true) {
            $attempt => ['email' => $email],
            $event instanceof PasskeyRegistered, $event instanceof PasskeyDeleted => ['passkey' => $event->passkey->name],
            default => [],
        })->log($name);
    }
}
