<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Roles mirror Epesi's Contacts/Access CommonData list (manager/employee)
     * plus filament-shield's own super_admin — see RoleSeeder, split out so
     * it can be run on its own (roles are needed infrastructure, not demo
     * data). The demo records themselves are in DemoDataSeeder, which the
     * setup wizard's "load demo data" option runs too.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);
        $admin->assignRole('super_admin');

        $this->callWith(DemoDataSeeder::class, ['admin' => $admin]);
    }
}
