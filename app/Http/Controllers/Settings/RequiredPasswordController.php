<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Accounts\Actions\ChangePassword;
use App\Domain\Accounts\Support\PasswordPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\RequiredPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where people land when their password expired or an admin asked them to choose a new one.
 */
class RequiredPasswordController extends Controller
{
    public function edit(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! PasswordPolicy::current()->requiresNewPassword($user)) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('auth/change-password', [
            'reason' => $user->password_change_required ? 'required' : 'expired',
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(RequiredPasswordRequest $request, ChangePassword $changePassword): RedirectResponse
    {
        $changePassword->handle($request->user(), $request->string('password')->toString(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return redirect()->route('dashboard');
    }
}
