<?php

namespace Epesi\Modules\Appearance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A named, admin-authored look: an accent colour and a density. Users don't
 * build their own — they pick one of these on the Appearance page
 * (UserAppearance) — so unlike RegionalSetting there is no per-user row of
 * values, just a per-user pointer at one of these.
 */
class Theme extends Model
{
    public const DENSITY_COMPACT = 'compact';

    public const DENSITY_COMFORTABLE = 'comfortable';

    public const FONT_SIZE_SMALL = 'small';

    public const FONT_SIZE_DEFAULT = 'default';

    public const FONT_SIZE_LARGE = 'large';

    protected $table = 'epesi_appearance_themes';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'accent_color',
        'density',
        'font_size',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * Only one row is ever the default: setting this one true un-sets every
     * other row's flag first. Not wrapped in a transaction — the same
     * single-process, no-serializable-isolation-needed spirit as
     * RegionalSetting's own default row.
     */
    protected static function booted(): void
    {
        static::saving(function (Theme $theme): void {
            if ($theme->is_default) {
                static::query()->where('id', '!=', $theme->getKey() ?? 0)->update(['is_default' => false]);
            }
        });
    }

    /** @return array<string, string> */
    public static function densities(): array
    {
        return [
            self::DENSITY_COMPACT => __('Compact'),
            self::DENSITY_COMFORTABLE => __('Comfortable'),
        ];
    }

    public function isCompact(): bool
    {
        return $this->density !== self::DENSITY_COMFORTABLE;
    }

    /** @return array<string, string> */
    public static function fontSizes(): array
    {
        return [
            self::FONT_SIZE_SMALL => __('Small'),
            self::FONT_SIZE_DEFAULT => __('Default'),
            self::FONT_SIZE_LARGE => __('Large'),
        ];
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    /**
     * The theme in effect for this user: their own choice
     * (UserAppearance), or the default theme, or null — a guest, a user who
     * hasn't chosen and no theme is marked default, or the Appearance module
     * itself has no themes yet. Every caller treats null as "today's shipped
     * look": no accent-colour override, and compact density (see
     * App\Support\Appearance\CurrentTheme and App\Http\Middleware\ApplyThemeColor).
     */
    public static function resolveFor(?User $user): ?self
    {
        if ($user) {
            $themeId = UserAppearance::query()->where('user_id', $user->getKey())->value('theme_id');

            if ($themeId && $theme = static::find($themeId)) {
                return $theme;
            }
        }

        return static::default();
    }
}
