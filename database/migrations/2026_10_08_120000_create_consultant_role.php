<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/**
 * The 'consultant' role: the main panel, but only the records tied to him
 * (HasOwnershipVisibility's consultant branch). RoleSeeder creates it on a
 * new install; this covers the installations that already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Role::firstOrCreate(['name' => 'consultant', 'guard_name' => config('auth.defaults.guard')]);
    }

    public function down(): void
    {
        // Left in place: users may have it assigned.
    }
};
