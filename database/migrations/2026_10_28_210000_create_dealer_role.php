<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/**
 * The 'dealer' role opens the dealer portal (AI-shared/features/access/dealer-portal.md).
 * RoleSeeder creates it on a new install; this covers the installations that
 * already exist, so Administration → Users has it to give.
 */
return new class extends Migration
{
    public function up(): void
    {
        Role::firstOrCreate(['name' => 'dealer', 'guard_name' => config('auth.defaults.guard')]);
    }

    public function down(): void
    {
        // Left in place: users may have it assigned.
    }
};
