<?php

namespace App\Console\Commands;

use App\Domain\Accounts\Enums\UserType;
use App\Domain\Accounts\Support\RoleCatalog;
use App\Domain\Entitlements\Enums\Limit;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * The first administrator of a new installation (or workspace). Asks for whatever isn't
 * passed as an option.
 */
#[Signature('kitedesk:create-admin {--name= : Full name} {--email= : Email address} {--password= : Password (prompted when left out)}')]
#[Description('Create an administrator account')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $input = [
            'name' => $this->option('name') ?: text('Name', required: true),
            'email' => $this->option('email') ?: text('Email', required: true),
            'password' => $this->option('password') ?: password('Password', required: true),
        ];

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::defaults()],
        ]);

        $seatError = PlanLimits::errorFor(Limit::AgentSeats);

        if ($validator->fails() || $seatError !== null) {
            foreach ([...$validator->errors()->all(), ...array_filter([$seatError])] as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = new User([
            'name' => $input['name'],
            'email' => mb_strtolower($input['email']),
            'password' => $input['password'],
        ]);
        $user->forceFill(['type' => UserType::Staff, 'email_verified_at' => now()])->save();
        $user->syncRoles([RoleCatalog::administrator()]);

        $this->components->info("Administrator {$user->email} created.");

        return self::SUCCESS;
    }
}
