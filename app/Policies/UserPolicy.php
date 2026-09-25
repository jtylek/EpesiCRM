<?php

namespace App\Policies;

use App\Models\User;

/**
 * Gates App\Filament\Administration\Resources\Users\UserResource. Only
 * super_admin reaches the "administration" panel at all
 * (User::canAccessPanel()), so this is defense-in-depth rather than the
 * only gate — matching ContactPolicy/CompanyPolicy, which also re-check
 * roles despite the owning panel's own canAccessPanel() already filtering
 * who gets that far.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function view(User $user, User $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, User $model): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->viewAny($user) && ! $model->is($user);
    }
}
