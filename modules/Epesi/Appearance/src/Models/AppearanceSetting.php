<?php

namespace Epesi\Modules\Appearance\Models;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * The one global appearance setting that isn't per-theme or per-user: the
 * application's own name, shown in the main panel's sidebar/topbar in place
 * of "epesi" (App\Providers\Filament\MainPanelProvider::brandName()) — what
 * identifies a particular installation as its owner's own business, not a
 * choice between look-and-feel like Theme. A single row (id 1 by
 * convention), the same shape as RegionalSetting's system-default row.
 */
class AppearanceSetting extends Model
{
    /**
     * The logo "kinds" and the column each is kept in: the login pages' logo and
     * the customer portal's, each for light mode and (-dark) for dark mode.
     */
    public const LOGO_COLUMNS = [
        'login' => 'login_logo',
        'login-dark' => 'login_logo_dark',
        'portal' => 'portal_logo',
        'portal-dark' => 'portal_logo_dark',
    ];

    protected $table = 'epesi_appearance_settings';

    /** @var list<string> */
    protected $fillable = [
        'app_name',
        'login_title',
        'portal_title',
        'login_logo',
        'portal_logo',
        'login_logo_dark',
        'portal_logo_dark',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    /** "epesi" until an administrator sets one of their own. */
    public static function appName(): string
    {
        return static::current()->app_name ?: 'epesi';
    }

    /** The login pages' heading: "epesi" until an administrator sets one of their own. */
    public static function loginTitle(): string
    {
        return static::current()->login_title ?: 'epesi';
    }

    public static function logoDisk(): Filesystem
    {
        return Storage::disk('local');
    }

    /** Path on the disk of the uploaded logo for 'login' or 'portal', or null. */
    public static function logoPath(string $kind): ?string
    {
        $column = static::LOGO_COLUMNS[$kind] ?? null;

        return $column ? (static::current()->{$column} ?: null) : null;
    }

    /** URL of the uploaded logo (changes when it is replaced, so browsers refetch), or null for the built-in one. */
    public static function logoUrl(string $kind): ?string
    {
        $path = static::logoPath($kind);

        if (! $path || ! static::logoDisk()->exists($path)) {
            return null;
        }

        return route('epesi.appearance.logo', ['kind' => $kind, 'v' => crc32($path)]);
    }

    /**
     * The uploaded logos of the 'login' or 'portal' page, by colour mode: a mode
     * with no picture of its own shows the other mode's, if there is one.
     *
     * @return array{light: ?string, dark: ?string}
     */
    public static function logoUrls(string $target): array
    {
        $light = static::logoUrl($target);
        $dark = static::logoUrl($target.'-dark');

        return ['light' => $light ?? $dark, 'dark' => $dark ?? $light];
    }

    /** The customer portal's title: "Customer Portal" until an administrator sets one. */
    public static function portalTitle(): string
    {
        return static::current()->portal_title ?: __('Customer Portal');
    }
}
