<?php

namespace Database\Seeders;

use App\Domain\Accounts\Support\RoleCatalog;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        RoleCatalog::sync();

        $this->call(DemoSeeder::class);
    }
}
