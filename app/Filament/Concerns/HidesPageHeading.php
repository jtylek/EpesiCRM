<?php

namespace App\Filament\Concerns;

use Illuminate\Contracts\Support\Htmlable;

/**
 * A resource page's own <h1> heading just repeats the resource label already
 * shown in the breadcrumb trail immediately above it (e.g. "Contacts" on the
 * List page; on a View page the record name appears three times over —
 * breadcrumb, heading, then the form itself). Suppressing it here, mixed into
 * every resource page's shared base class alongside HasResourceIconBreadcrumb
 * (and into every other page alongside HasPageIconBreadcrumb),
 * lets Filament's header (a flex row once the heading's gone) put the header
 * actions (New record, Edit, Clone, ...) inline with the breadcrumb instead of
 * on their own line below it.
 *
 * getTitle() (browser tab title) is untouched — only the visible heading.
 */
trait HidesPageHeading
{
    public function getHeading(): string|Htmlable|null
    {
        return null;
    }
}
