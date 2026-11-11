<?php

namespace App\Filament\UserSettings\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Filament\Concerns\UsesEpesiFormLayout;
use App\Models\User;
use App\Support\Demo;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Lets any signed-in user change their own password (User Settings →
 * Password). The current password is required; the change is logged like an
 * administrator's reset (the User model's activity hook).
 */
class PasswordSettings extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;
    use UsesEpesiFormLayout;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected string $view = 'filament.pages.calendar-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string
    {
        return __('Password');
    }

    public static function getNavigationLabel(): string
    {
        return __('Password');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('Change password'))
                    ->schema([
                        TextInput::make('current_password')
                            ->label(__('Current password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->currentPassword(),
                        TextInput::make('password')
                            ->label(__('New password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->different('current_password')
                            ->confirmed(),
                        TextInput::make('password_confirmation')
                            ->label(__('Confirm new password'))
                            ->password()
                            ->revealable()
                            ->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        if (Demo::enabled()) {
            Demo::unavailable();

            return;
        }

        $data = $this->form->getState();

        /** @var User $user */
        $user = Auth::user();
        $user->update(['password' => Hash::make($data['password'])]);

        // Keep this session signed in (AuthenticateSession compares the hash).
        session()->put(['password_hash_'.Filament::getAuthGuard() => $user->getAuthPassword()]);

        $this->form->fill();

        Notification::make()->success()->title(__('Password changed'))->send();
    }
}
