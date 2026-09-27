<?php

namespace App\Filament\Administration\Resources\Users\Pages;

use App\Filament\Administration\Resources\Users\UserActions;
use App\Filament\Administration\Resources\Users\UserResource;
use App\Support\Auth\UserActivity;
use Epesi\Modules\RecordBrowser\Filament\Pages\EditRecord;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @var array<int, string> the roles before the form is saved, for the History */
    protected array $rolesBefore = [];

    protected function beforeSave(): void
    {
        $this->rolesBefore = $this->record->getRoleNames()->all();
    }

    /** Roles are saved with the form's relationships, after the record itself: name, e-mail and active are logged as they change, roles here. */
    protected function afterSave(): void
    {
        $this->record->unsetRelation('roles');

        UserActivity::rolesChanged($this->record, $this->rolesBefore, $this->record->getRoleNames()->all());
    }

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
