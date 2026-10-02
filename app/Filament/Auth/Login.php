<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Support\Demo;
use App\Support\Locale\Locales;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;

/**
 * Filament's login page, except in demo mode (App\Support\Demo): then it is
 * Epesi's demo login, a "Log in as" choice of the demo accounts
 * (config/demo.php) and no password, no "Remember me" — and a language
 * choice, kept for the visitor rather than the shared account.
 *
 * Only an account on that list can be chosen — whatever the request says —
 * and never one with super_admin, so the demo login can't open more than the
 * list offers.
 */
class Login extends BaseLogin
{
    /**
     * The application's name at the very top of the login card, above the brand.
     */
    public function mount(): void
    {
        parent::mount();

        FilamentView::registerRenderHook(
            PanelsRenderHook::SIMPLE_PAGE_START,
            fn (): HtmlString => new HtmlString(
                '<div class="mb-4 text-center text-xl font-medium text-gray-500 dark:text-gray-400">'
                .e(__('Business Information Manager')).'</div>'
            ),
            scopes: static::class,
        );
    }

    public function form(Schema $schema): Schema
    {
        if (! Demo::enabled()) {
            return parent::form($schema);
        }

        return $schema->components([
            // The visitor's language, not the demo account's (Demo::locale()).
            Select::make('locale')
                ->label('Language')
                ->options(Locales::available())
                ->default(fn (): string => app()->getLocale())
                ->selectablePlaceholder(false)
                ->live()
                ->afterStateUpdated(function (?string $state): void {
                    Demo::rememberLocale($state);

                    // The page was drawn in the old language.
                    $this->redirect(Filament::getLoginUrl());
                }),
            Select::make('email')
                ->label('Log in as')
                ->options(fn (): array => Demo::loginOptions())
                ->required()
                ->helperText(__('The demo data is reset every day. When you log in, your IP address and browser are recorded to count demo visits.')),
        ]);
    }

    public function authenticate(): ?LoginResponse
    {
        if (! Demo::enabled()) {
            return parent::authenticate();
        }

        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $email = (string) ($this->form->getState()['email'] ?? '');

        $user = array_key_exists($email, config('demo.users', []))
            ? User::query()->where('email', $email)->first()
            : null;

        if (! $user || $user->hasRole('super_admin') || ! $this->isUserAllowedToAccessPanel($user)) {
            $this->throwFailureValidationException();
        }

        Filament::auth()->login($user);

        session()->regenerate();

        return app(LoginResponse::class);
    }
}
