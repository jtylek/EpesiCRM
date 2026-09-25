<?php

namespace Tests\Concerns;

use App\Models\User;
use Database\Seeders\RoleSeeder;

trait SignsInUsers
{
    protected function userWithRole(string $role = 'employee', array $attributes = []): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
