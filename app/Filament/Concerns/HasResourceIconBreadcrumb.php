<?php

namespace App\Filament\Concerns;

use Filament\Support\Enums\IconSize;
use Illuminate\Support\HtmlString;

/**
 * Prefixes a resource page's first breadcrumb ("Contacts" in
 * "Contacts > List") with the resource's own navigation icon — the same
 * icon shown for it in the sidebar. Mixed into every resource page's shared
 * base class (ListRecords, ViewRecord, EditRecord, CreateRecord in this
 * namespace's parent, App\Filament\Pages) rather than overriding getHeading()
 * on List alone, so it appears on every page type for a resource instead of
 * just its List page.
 *
 * Also leaves out the record title Filament puts between the two on a View or
 * Edit page: "Tasks > View", not "Tasks > Send offer > View".
 */
trait HasResourceIconBreadcrumb
{
    /**
     * Filament's Resources\Pages\Page::getBreadcrumbs(), which
     * InteractsWithRecord overrides to add the record title. The page shows
     * the title in its first row anyway, and a long one wrapped the header
     * onto several lines.
     */
    public function getBreadcrumbs(): array
    {
        return [
            ...$this->getResourceBreadcrumbs(),
            $this->getBreadcrumb(),
        ];
    }

    public function getResourceBreadcrumbs(): array
    {
        $breadcrumbs = parent::getResourceBreadcrumbs();

        $icon = static::getResource()::getNavigationIcon();

        if (blank($icon) || $breadcrumbs === []) {
            return $breadcrumbs;
        }

        $firstUrl = array_key_first($breadcrumbs);

        $breadcrumbs[$firstUrl] = new HtmlString(
            view('filament.components.heading-with-icon', [
                'icon' => $icon,
                'heading' => $breadcrumbs[$firstUrl],
                'size' => IconSize::Medium,
            ])->render()
        );

        return $breadcrumbs;
    }
}
