<?php

namespace Epesi\Modules\Appearance\Models;

use Illuminate\Database\Eloquent\Model;

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
    protected $table = 'epesi_appearance_settings';

    /** @var list<string> */
    protected $fillable = [
        'app_name',
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
}
