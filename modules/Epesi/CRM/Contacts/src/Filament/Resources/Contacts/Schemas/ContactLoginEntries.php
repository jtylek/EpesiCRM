<?php

namespace Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Schemas;

use App\Models\User;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;

/**
 * The "Login" entries, factored out of the main infolist so they can be
 * reused as their own tab (see Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ViewContact)
 * instead of sitting in a static card above the relation manager tabs.
 */
class ContactLoginEntries
{
    /**
     * @return array<TextEntry>
     */
    public static function components(): array
    {
        return [
            TextEntry::make('user.email')->label('Linked User')->placeholder(__('- none -')),
            TextEntry::make('user.roles.name')->label('Roles')->badge()->placeholder(__('-')),
        ];
    }

    /**
     * Epesi's inline Set Password/Confirm Password QFfield_callbacks on the
     * contact form become a real Action here instead, hashed via the
     * framework rather than handled by bespoke form logic. Lives on the
     * "Login" tab (as a Section header action) rather than as a table row
     * action, since resetting a password is a property of the linked login,
     * not of the contact record itself.
     */
    public static function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->label('Reset Password')
            ->icon('heroicon-o-key')
            ->visible(fn (Contact $record): bool => $record->user_id !== null)
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
            ->action(function (Contact $record, array $data): void {
                $record->user->update(['password' => Hash::make($data['password'])]);

                Notification::make()
                    ->title(__('Password updated'))
                    ->success()
                    ->send();
            });
    }

    /**
     * The linked login's username is its email column (see User::$fillable
     * and the panel login form) — there was previously no way to change it
     * once imported, only view it read-only via `components()` above.
     * Lives next to Reset Password for the same reason that one does: it's
     * a property of the linked login, not of the contact record itself.
     */
    public static function changeUsernameAction(): Action
    {
        return Action::make('changeUsername')
            ->label('Change Username')
            ->icon('heroicon-o-user')
            ->visible(fn (Contact $record): bool => $record->user_id !== null)
            ->schema(fn (Contact $record): array => [
                TextInput::make('email')
                    ->label('Username')
                    ->email()
                    ->required()
                    ->unique(User::class, 'email', ignorable: $record->user)
                    ->default($record->user?->email),
            ])
            ->action(function (Contact $record, array $data): void {
                $record->user->update(['email' => $data['email']]);

                Notification::make()
                    ->title(__('Username updated'))
                    ->success()
                    ->send();
            });
    }
}
