<?php

namespace Epesi\Modules\Appearance\Filament\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Filament\Concerns\UsesEpesiFormLayout;
use BackedEnum;
use Epesi\Modules\Appearance\Models\Theme;
use Epesi\Modules\Appearance\Models\UserAppearance;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * A personal-preferences page, not a resource, next to Regional Settings:
 * every user picks one of the admin-authored Themes (Administration →
 * Themes) for themselves, or nothing (falls back to whichever one the admin
 * marked default). See AI-shared/Epesi-custom-themes.md.
 */
class Appearance extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;
    use UsesEpesiFormLayout;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    // Lower than every other user-settings page's (unset, so -1) default, so
    // this is consistently the first navigation item and thus where the bare
    // /user-settings panel path (RedirectToHomeController) lands.
    protected static ?int $navigationSort = -2;

    protected string $view = 'epesi-appearance::appearance';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string
    {
        return __('Appearance');
    }

    public static function getNavigationLabel(): string
    {
        return __('Appearance');
    }

    public function mount(): void
    {
        $this->form->fill([
            'theme_id' => UserAppearance::themeIdFor(Auth::user()),
        ]);
    }

    /** No themes to pick from until an administrator creates one. */
    public function hasThemes(): bool
    {
        return Theme::query()->exists();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Radio::make('theme_id')
                    ->label(__('Theme'))
                    ->options(fn (): array => Theme::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->descriptions(fn (): array => Theme::query()->orderBy('name')->get()
                        ->mapWithKeys(fn (Theme $theme): array => [$theme->getKey() => Theme::densities()[$theme->density].' · '.Theme::fontSizes()[$theme->font_size]])
                        ->all())
                    ->required(),
            ]);
    }

    public function save(): void
    {
        UserAppearance::choose(Auth::user(), $this->form->getState()['theme_id']);

        Notification::make()->success()->title(__('Appearance saved'))->send();

        // The chosen theme's density and font size need a full page load to
        // take effect (CurrentTheme::appearanceScript() runs once, in <head>).
        $this->redirect(static::getUrl());
    }
}
