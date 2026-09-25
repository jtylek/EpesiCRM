<?php

namespace App\Filament\Concerns;

use Filament\Support\Enums\IconSize;
use Illuminate\Support\HtmlString;

/**
 * HasResourceIconBreadcrumb's counterpart for a page that isn't part of a
 * resource (Dashboard, Calendar, a module's own page such as Watched).
 * Filament gives such a page no breadcrumb, only a large <h1> of its title, so
 * its header looked nothing like a resource's "(icon) Contacts > List". This
 * adds the page's own sidebar icon and label as its breadcrumb; mixed in
 * alongside HidesPageHeading, every page's header then reads the same.
 *
 * tests/Feature/PageHeaderTest fails for any panel page that misses it.
 */
trait HasPageIconBreadcrumb
{
    public function getBreadcrumbs(): array
    {
        $icon = static::getNavigationIcon();
        $label = static::getNavigationLabel();

        return [
            ...parent::getBreadcrumbs(),
            static::getUrl() => blank($icon) ? $label : new HtmlString(
                view('filament.components.heading-with-icon', [
                    'icon' => $icon,
                    'heading' => $label,
                    'size' => IconSize::Medium,
                ])->render()
            ),
        ];
    }
}
