<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * The Spatie roles (super_admin/manager/employee, and customer for the portal — see
 * DatabaseSeeder's own docblock for where they come from) are infrastructure
 * every login needs to pass User::canAccessPanel(), not demo business data.
 * Split out so `php artisan import:legacy` can be run against a database
 * that has these roles but none of DatabaseSeeder's demo Companies/Contacts —
 * useful when re-importing with ids aligned to the legacy database.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'super_admin']);
        Role::firstOrCreate(['name' => 'manager']);
        Role::firstOrCreate(['name' => 'employee']);
        // Opens the customer portal, not the main panel (AI-shared/Customer-portal.md).
        Role::firstOrCreate(['name' => 'customer']);
    }
}
