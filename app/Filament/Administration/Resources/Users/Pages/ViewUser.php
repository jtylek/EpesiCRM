<?php

namespace App\Filament\Administration\Resources\Users\Pages;

use App\Filament\Administration\Resources\Users\UserActions;
use App\Filament\Administration\Resources\Users\UserResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon(Heroicon::OutlinedPencil),
            UserActions::changeUsername(),
            UserActions::resetPassword(),
            UserActions::logInAs(),
            UserActions::toggleActive(),
        ];
    }
}
