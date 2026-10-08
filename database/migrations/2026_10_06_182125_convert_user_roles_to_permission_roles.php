<?php

use App\Domain\Accounts\Support\RoleCatalog;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the fixed `users.role` column with `users.type` (staff or customer) plus a
 * configurable role for staff. Former admins, agents and light agents get the built-in
 * role of the same name, so nobody's access changes.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const array ROLES = [
        'admin' => RoleCatalog::ADMINISTRATOR,
        'agent' => RoleCatalog::AGENT,
        'light_agent' => RoleCatalog::LIGHT_AGENT,
    ];

    public function up(): void
    {
        RoleCatalog::sync();

        Schema::table('users', function (Blueprint $table) {
            $table->string('type')->default('customer')->after('email')->index();
        });

        $roleIds = DB::table('roles')->where('guard_name', 'web')->pluck('id', 'name');

        foreach (self::ROLES as $old => $role) {
            DB::table('users')->where('role', $old)->orderBy('id')->each(function (object $user) use ($roleIds, $role): void {
                DB::table('users')->where('id', $user->id)->update(['type' => 'staff']);
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $roleIds[$role],
                    'model_type' => (new User)->getMorphClass(),
                    'model_id' => $user->id,
                ]);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer')->after('email')->index();
        });

        foreach (self::ROLES as $old => $role) {
            DB::table('users')
                ->whereIn('id', DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', $role)
                    ->select('model_has_roles.model_id'))
                ->update(['role' => $old]);
        }

        DB::table('users')->where('type', 'staff')->where('role', 'customer')->update(['role' => 'light_agent']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
