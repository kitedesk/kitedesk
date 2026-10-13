<?php

namespace App\Domain\Accounts\Support;

use App\Domain\Support\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rules\Password;

/**
 * The rules passwords must follow, when they expire, how many old ones can't be reused, and
 * whether staff must use two-factor authentication. Set by admins on the Security page.
 */
final readonly class PasswordPolicy
{
    public const string SETTING = 'password_policy';

    /**
     * The most previous passwords that can be remembered (and refused).
     */
    public const int MAX_REMEMBERED = 24;

    public function __construct(
        public int $minLength = 8,
        public bool $mixedCase = false,
        public bool $numbers = false,
        public bool $symbols = false,
        public bool $uncompromised = false,
        public ?int $expiresAfterDays = null,
        public int $rememberLast = 0,
        public bool $requireTwoFactorForStaff = false,
    ) {}

    public static function current(): self
    {
        $setting = Setting::get(self::SETTING);

        if (! is_array($setting)) {
            return self::fallback();
        }

        return new self(
            (int) ($setting['min_length'] ?? 8),
            (bool) ($setting['mixed_case'] ?? false),
            (bool) ($setting['numbers'] ?? false),
            (bool) ($setting['symbols'] ?? false),
            (bool) ($setting['uncompromised'] ?? false),
            isset($setting['expires_after_days']) ? (int) $setting['expires_after_days'] : null,
            (int) ($setting['remember_last'] ?? 0),
            (bool) ($setting['require_two_factor_for_staff'] ?? false),
        );
    }

    /**
     * Until an admin saves a policy: strong passwords in production, Laravel's defaults elsewhere.
     */
    public static function fallback(): self
    {
        return app()->isProduction() ? new self(12, true, true, true, true) : new self;
    }

    public function save(): void
    {
        Setting::put(self::SETTING, [
            'min_length' => $this->minLength,
            'mixed_case' => $this->mixedCase,
            'numbers' => $this->numbers,
            'symbols' => $this->symbols,
            'uncompromised' => $this->uncompromised,
            'expires_after_days' => $this->expiresAfterDays,
            'remember_last' => $this->rememberLast,
            'require_two_factor_for_staff' => $this->requireTwoFactorForStaff,
        ]);
    }

    /**
     * The validation rule for new passwords.
     */
    public function rule(): Password
    {
        return Password::min($this->minLength)
            ->when($this->mixedCase, fn (Password $rule) => $rule->mixedCase())
            ->when($this->numbers, fn (Password $rule) => $rule->numbers())
            ->when($this->symbols, fn (Password $rule) => $rule->symbols())
            ->when($this->uncompromised, fn (Password $rule) => $rule->uncompromised());
    }

    /**
     * When the person's password stops working, or null when it doesn't expire.
     */
    public function expiresAt(User $user): ?CarbonImmutable
    {
        if ($this->expiresAfterDays === null || $user->password_changed_at === null) {
            return null;
        }

        return CarbonImmutable::parse($user->password_changed_at)->addDays($this->expiresAfterDays);
    }

    public function hasExpired(User $user): bool
    {
        return $this->expiresAt($user)?->isPast() ?? false;
    }

    /**
     * Whether the person must choose a new password before doing anything else.
     */
    public function requiresNewPassword(User $user): bool
    {
        return $user->password_change_required || $this->hasExpired($user);
    }

    /**
     * Whether the person must turn on two-factor authentication (or add a passkey) first.
     */
    public function requiresTwoFactor(User $user): bool
    {
        return $this->requireTwoFactorForStaff
            && $user->isStaff()
            && ! $user->hasEnabledTwoFactorAuthentication()
            && $user->passkeys()->doesntExist();
    }
}
