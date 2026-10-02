<?php

namespace Epesi\Modules\Appearance;

use App\Models\User;
use App\Support\Appearance\AppName;
use App\Support\Appearance\CurrentTheme;
use Epesi\Modules\Appearance\Models\AppearanceSetting;
use Epesi\Modules\Appearance\Models\Theme;
use Epesi\Modules\Appearance\Models\UserAppearance;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Once;
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
        // Both are asked several times per page (the layout, the theme
        // middleware, the brand), so once() keeps the answer for the request;
        // saving any of the three models forgets it.
        CurrentTheme::resolveUsing(fn (?User $user): ?array => once(function () use ($user): ?array {
            $theme = Theme::resolveFor($user);

            return $theme ? [
                'accent_color' => $theme->accent_color,
                'compact' => $theme->isCompact(),
                'font_size' => $theme->font_size,
            ] : null;
        }));

        // Same reasoning, for the one global setting that isn't a Theme.
        AppName::resolveUsing(fn (): string => once(fn (): string => AppearanceSetting::appName()));

        foreach ([Theme::class, UserAppearance::class, AppearanceSetting::class] as $model) {
            $model::saved(fn () => Once::flush());
            $model::deleted(fn () => Once::flush());
        }
    }
}
