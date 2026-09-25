<?php

namespace App\Filament\Administration\Resources\Users\Pages;

use App\Filament\Administration\Resources\Users\UserActions;
use App\Filament\Administration\Resources\Users\UserResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\EditRecord;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Same header layout as the shared base (Save/Cancel), except Delete is
     * replaced by the same deactivate toggle List/View offer — Epesi disables
     * a login, it never deletes one, so no page should offer a real delete.
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Save')->icon(Heroicon::OutlinedCheck)->color('success')->formId('form'),
            ViewAction::make()->label('Cancel')->color('gray')->icon(Heroicon::OutlinedXMark),
            UserActions::toggleActive(),
        ];
    }
}
