<?php

namespace App\Support\Appearance;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * What a signed-in user's theme resolves to — an accent colour, a density
 * and a font size — without the core app naming the Appearance module
 * directly.
 * `Epesi\Modules\Appearance\AppearanceServiceProvider` plugs in the real
 * resolver when that module is registered and enabled; until then (a fresh
 * checkout that hasn't run `module:register` yet, an upgrade before
 * `epesi:update`, the module disabled) `forUser()` returns null and every
 * caller falls back to today's shipped look — no colour override, compact
 * density — never a missing-class or missing-table error.
 *
 * The same hook pattern as App\Support\Locale\Locales for RegionalSettings:
 * a core middleware/panel provider that named the module's classes directly
 * would crash the instant it's referenced, before the module is registered —
 * exactly what happened here (see AI-shared/Epesi-custom-themes.md).
 */
class CurrentTheme
{
    /** @var Closure(?User): (array{accent_color: ?string, compact: bool, font_size: string}|null)|null */
    protected static ?Closure $resolver = null;

    /**
     * @param  (Closure(?User): (array{accent_color: ?string, compact: bool, font_size: string}|null))|null  $resolver
     */
    public static function resolveUsing(?Closure $resolver): void
    {
        static::$resolver = $resolver;
    }

    /** @return array{accent_color: ?string, compact: bool, font_size: string}|null */
    public static function forUser(?User $user): ?array
    {
        return static::$resolver ? (static::$resolver)($user) : null;
    }

    /**
     * Livewire replaces <html> attributes on navigation but only runs an
     * identical head script once. Keep density and font size in per-response
     * meta tags and reapply them after navigation, including cached
     * back/forward visits.
     */
    public static function appearanceScript(?User $user): Htmlable
    {
        $appearance = static::forUser($user);
        $density = ($appearance['compact'] ?? true) ? 'compact' : 'comfortable';
        $fontSize = $appearance['font_size'] ?? 'default';

        return new HtmlString('<meta name="epesi-density" content="'.$density.'">'.
            '<meta name="epesi-font-size" content="'.$fontSize.'">'.<<<'HTML'
<script>
(() => {
    const applyAppearance = () => {
        const density = document.querySelector('meta[name="epesi-density"]')?.content;
        document.documentElement.classList.toggle('epesi-compact', density === 'compact');

        const fontSize = document.querySelector('meta[name="epesi-font-size"]')?.content;
        document.documentElement.classList.toggle('epesi-font-sm', fontSize === 'small');
        document.documentElement.classList.toggle('epesi-font-lg', fontSize === 'large');
    };

    applyAppearance();
    document.addEventListener('livewire:navigated', applyAppearance);
})();
</script>
HTML);
    }
}
