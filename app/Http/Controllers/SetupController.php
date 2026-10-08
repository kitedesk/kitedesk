<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Actions\CreateAdministrator;
use App\Domain\Branding\Branding;
use App\Domain\Support\Installation;
use App\Http\Requests\Setup\CompleteSetupRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The first-run screen of a new installation: names the helpdesk and creates the first
 * administrator. It disappears once the installation has staff.
 */
class SetupController extends Controller
{
    public function show(Request $request): Response
    {
        abort_unless(Installation::needsSetup(), 404);

        return Inertia::render('auth/setup', [
            'code' => $request->string('code')->toString(),
            'helpdeskName' => Branding::current()->name(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(CompleteSetupRequest $request, CreateAdministrator $createAdministrator): RedirectResponse
    {
        $admin = DB::transaction(function () use ($request, $createAdministrator) {
            // Checked again, in case someone else finished setting up meanwhile.
            abort_unless(Installation::needsSetup(), 404);

            (new Branding(name: $request->string('helpdesk_name')->trim()->toString()))->save();

            return $createAdministrator->handle(
                $request->string('name')->trim()->toString(),
                $request->string('email')->trim()->toString(),
                $request->string('password')->toString(),
                $request->validated('timezone'),
            );
        });

        Auth::login($admin);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your helpdesk is ready. Next, connect a mailbox and invite your team.')]);

        return redirect()->route('admin.index');
    }
}
