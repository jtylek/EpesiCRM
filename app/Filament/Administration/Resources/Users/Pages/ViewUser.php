<?php

namespace App\Filament\Administration\Resources\Users\Pages;

use App\Filament\Administration\Resources\Users\UserActions;
use App\Filament\Administration\Resources\Users\UserResource;
use App\Models\User;
use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon(Heroicon::OutlinedPencil),
            $this->resetPasswordAction(),
            UserActions::logInAs(),
            UserActions::toggleActive(),
        ];
    }

    /**
     * Password isn't on the main Edit form (see UserForm) — changing it for
     * an existing user is a deliberate separate action instead, the same
     * way ContactLoginEntries::resetPasswordAction() handles it for a
     * Contact's linked login.
     */
    private function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->label('Reset Password')
            ->icon(Heroicon::OutlinedKey)
            ->schema([
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8)
                    ->confirmed(),
                TextInput::make('password_confirmation')
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->action(function (User $record, array $data): void {
                $record->update(['password' => Hash::make($data['password'])]);

                Notification::make()
                    ->title(__('Password updated'))
                    ->success()
                    ->send();
            });
    }
}
