<?php

namespace App\Filament\UserSettings\Pages;

use App\Filament\Administration\Pages\About as AdministrationAbout;
use App\Models\User;
use Filament\Facades\Filament;

/**
 * User Settings → About: the same version, license and support information as
 * Administration → About, and the page the panel opens on. Registering the
 * installation stays with administrators.
 */
class About extends AdministrationAbout
{
    protected static ?int $navigationSort = -5;

    protected function registration(): array
    {
        $user = auth()->user();

        return $user instanceof User && $user->canAccessPanel(Filament::getPanel('administration'))
            ? parent::registration()
            : [];
    }
}
