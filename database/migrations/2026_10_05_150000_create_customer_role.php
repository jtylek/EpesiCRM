<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/**
 * The 'customer' role opens the customer portal (AI-shared/Customer-portal.md)
 * but only the tests ever created it, so Administration → Users had no such
 * role to give. RoleSeeder creates it on a new install; this covers the
 * installations that already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => config('auth.defaults.guard')]);
    }

    public function down(): void
    {
        // Left in place: users may have it assigned.
    }
};
