<?php

namespace App\Filament\Administration\Resources\Users;

use App\Models\User;
use App\Support\Auth\NewAccountMailer;
use App\Support\Impersonation;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * The operations behind the Users screen, shared by the table rows and the
 * View page header so both offer the same ones — mirrors ModuleActions.
 */
class UserActions
{
    public static function logInAs(): Action
    {
        return Action::make('logInAs')
            ->label('Log in as user')
            ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
            ->visible(fn (User $record): bool => Impersonation::allowed($record))
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => __('Log in as :name', ['name' => $record->name]))
            ->modalDescription(fn (User $record): string => __('You will work in epesi as :name, with their permissions, without their password. A bar at the top of every page takes you back to your own account.', ['name' => $record->name]))
            ->modalSubmitActionLabel('Log in')
            ->action(function (User $record, Action $action): void {
                Impersonation::start($record);

                // A full page load: the new user may not reach this panel.
                $action->redirect(Filament::getPanel('main')->getUrl(), navigate: false);
            });
    }

    /**
     * Password isn't on the main Edit form (see UserForm): changing it for an
     * existing user is a deliberate separate action, which used to sit on the
     * Login tab of the user's contact (Epesi's Set Password on the contact
     * form). All account management happens here, not on the contact.
     *
     * Left blank, the user is e-mailed a link to choose their own password
     * instead (NewAccountMailer, as when a user is created), so nobody has to
     * know it. The password isn't logged, only that it changed or that a link
     * was sent (UserActivity).
     */
    public static function resetPassword(): Action
    {
        return Action::make('resetPassword')
            ->label('Reset Password')
            ->icon(Heroicon::OutlinedKey)
            ->schema([
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->confirmed()
                    ->helperText(__('Leave blank to e-mail them a link to choose their own password.')),
                TextInput::make('password_confirmation')
                    ->password()
                    ->revealable(),
            ])
            ->action(function (User $record, array $data): void {
                if (blank($data['password'] ?? null)) {
                    static::sendPasswordLink($record);

                    return;
                }

                $record->update(['password' => Hash::make($data['password'])]);

                Notification::make()
                    ->title(__('Password updated'))
                    ->success()
                    ->send();
            });
    }

    /** Tells the administrator what came of e-mailing the link: sent, or why not. */
    protected static function sendPasswordLink(User $record): void
    {
        if ($reason = NewAccountMailer::whyNoLink($record)) {
            Notification::make()
                ->title(__('No e-mail was sent'))
                ->body($reason.' '.__('Type a password here instead, or change that first.'))
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        $notification = match (NewAccountMailer::sendSetPasswordLink($record)) {
            NewAccountMailer::SENT => Notification::make()
                ->title(__('An e-mail with a link to choose a password was sent to :email', ['email' => $record->email]))
                ->success(),
            NewAccountMailer::THROTTLED => Notification::make()
                ->title(__('A link was sent a moment ago'))
                ->body(__('Wait a minute before sending another, or type a password here instead.'))
                ->warning(),
            default => Notification::make()
                ->title(__('The e-mail could not be sent'))
                ->body(__('Check Administration → Mail Server, or type a password here instead.'))
                ->warning()
                ->persistent(),
        };

        $notification->send();
    }

    /**
     * The username is the e-mail address the user signs in with. It is the
     * login's own: the contact's e-mail address, where the user was made from
     * one, stays as it is.
     */
    public static function changeUsername(): Action
    {
        return Action::make('changeUsername')
            ->label('Change Username')
            ->icon(Heroicon::OutlinedUser)
            ->fillForm(fn (User $record): array => ['email' => $record->email])
            ->schema(fn (User $record): array => [
                TextInput::make('email')
                    ->label('Username')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(User::class, 'email', ignorable: $record),
            ])
            ->action(function (User $record, array $data): void {
                $record->update(['email' => $data['email']]);

                Notification::make()
                    ->title(__('Username updated'))
                    ->success()
                    ->send();
            });
    }

    public static function toggleActive(): Action
    {
        return Action::make('toggleActive')
            ->label(fn (User $record): string => $record->active ? __('Deactivate') : __('Reactivate'))
            ->icon(fn (User $record): Heroicon => $record->active ? Heroicon::OutlinedUserMinus : Heroicon::OutlinedUserPlus)
            ->color(fn (User $record): string => $record->active ? 'danger' : 'success')
            // Never on the account you're currently signed in as — matching
            // UserPolicy::delete()'s self-protection, so a super_admin can't
            // lock themselves out with no one left to undo it.
            ->visible(fn (User $record): bool => ! $record->is(Auth::user()))
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => $record->active
                ? 'They can no longer log in. Records they created and the Login Audit trail are left untouched — Epesi disables logins, it never deletes them.'
                : 'They can log in again with their existing password.')
            ->action(function (User $record): void {
                $record->update(['active' => ! $record->active]);

                Notification::make()
                    ->success()
                    ->title($record->active ? __('User reactivated') : __('User deactivated'))
                    ->send();
            });
    }
}
