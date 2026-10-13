<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Accounts\Support\PasswordPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSecurityPolicyRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The password policy and two-factor requirement for everyone who signs in.
 */
class SecurityPolicyController extends Controller
{
    public function edit(): Response
    {
        $policy = PasswordPolicy::current();

        return Inertia::render('admin/security/edit', [
            'policy' => [
                'min_length' => $policy->minLength,
                'mixed_case' => $policy->mixedCase,
                'numbers' => $policy->numbers,
                'symbols' => $policy->symbols,
                'uncompromised' => $policy->uncompromised,
                'expires_after_days' => $policy->expiresAfterDays,
                'remember_last' => $policy->rememberLast,
                'require_two_factor_for_staff' => $policy->requireTwoFactorForStaff,
            ],
            'maxRemembered' => PasswordPolicy::MAX_REMEMBERED,
            'staffWithoutTwoFactor' => User::query()->staff()->active()
                ->whereNull('two_factor_confirmed_at')
                ->whereDoesntHave('passkeys')
                ->count(),
        ]);
    }

    public function update(SaveSecurityPolicyRequest $request): RedirectResponse
    {
        $request->policy()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Security settings saved.')]);

        return back();
    }
}
