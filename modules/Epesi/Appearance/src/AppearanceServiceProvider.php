<?php

namespace Epesi\Modules\Appearance;

use App\Models\User;
use App\Support\Appearance\AppName;
use App\Support\Appearance\CurrentTheme;
use Epesi\Modules\Appearance\Models\AppearanceSetting;
use Epesi\Modules\Appearance\Models\Theme;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppearanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The core morph map (AppServiceProvider) is enforced, so an
        // unmapped model throws the moment anything polymorphic touches it —
        // ThemeResource's View page reuses RecordBrowser's shared ViewRecord
        // base (see Filament\Administration\Resources\Themes\ThemeResource),
        // whose addon-tab machinery (here, PriorityList) does exactly that
        // for every resource built on it, whether or not the resource itself
        // uses that addon. Registering the alias here rather than in
        // AppServiceProvider is what lets this model live in a module
        // without orphaning any polymorphic reference to it — same as every
        // CRM module's own ServiceProvider.
        Relation::morphMap(['theme' => Theme::class]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'epesi-appearance');

        // Only from here does App\Support\Appearance\CurrentTheme resolve to
        // anything — see its docblock for why core code doesn't call Theme
        // directly.
        CurrentTheme::resolveUsing(function (?User $user): ?array {
            $theme = Theme::resolveFor($user);

            return $theme ? [
                'accent_color' => $theme->accent_color,
                'compact' => $theme->isCompact(),
                'font_size' => $theme->font_size,
            ] : null;
        });

        // Same reasoning, for the one global setting that isn't a Theme.
        AppName::resolveUsing(fn (): string => AppearanceSetting::appName());
    }
}
