<?php

namespace App\Filament\Administration\Resources\Users;

use App\Models\User;
use App\Support\Impersonation;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

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
