<?php

namespace App\Providers\Filament\Concerns;

use Illuminate\Support\HtmlString;

/**
 * Panel-wide "boxed row" styling for inline-label field rows on View/Edit/Create pages,
 * echoing old Epesi's shaded-label-cell table layout. Scoped to the `-has-inline-label`
 * modifier classes Filament already adds, so it automatically applies everywhere inline
 * labels are on (every resource's View/Edit/Create, panel-wide) and skips the tabs that
 * deliberately opt out (Record Info, Contact's Login) without any extra selectors.
 *
 * Edit/Create only box the label column, not the value column: the value side there is
 * already a native input with its own border/background, so boxing it too would nest a
 * box inside a box. View boxes both sides since a plain TextEntry has no chrome of its own.
 *
 * Two extra fixes on top of the box/spacing rules: a fixed label-column width (both View
 * and Edit/Create) so a `columnSpanFull()` field's label (e.g. Memo, Description) lines up
 * with every other row's label instead of being computed as 1/3 of that one field's own
 * (much wider) row; and `align-items: stretch` (View only — see below) so an empty/short
 * value box grows to match its label's height instead of collapsing to a sliver.
 *
 * `.rb-column-flow` wraps a RecordBrowser View grid whose fields read down the first column and
 * then down the second (RecordsetResource::flowIntoColumns): from the `lg` breakpoint up its
 * `--rb-rows` sets how many rows the first column takes, and below that it stays one column.
 *
 * The tight schema gap is for field rows; a grid of widgets (the dashboard's, a page's
 * header widgets — or their lazy-load placeholders) gets a card-sized gap back.
 */
trait HasBoxedFieldStyles
{
    protected function boxedFieldStyles(): HtmlString
    {
        return new HtmlString('<style>'
            .'.fi-in-entry.fi-in-entry-has-inline-label>.fi-in-entry-label-col{background-color:var(--gray-100)!important;border:1px solid var(--gray-200)!important;padding:.25rem .625rem!important}'
            .'.fi-in-entry.fi-in-entry-has-inline-label>.fi-in-entry-content-col{background-color:var(--gray-50)!important;border:1px solid var(--gray-200)!important;padding:.25rem .625rem!important}'
            .'.dark .fi-in-entry.fi-in-entry-has-inline-label>.fi-in-entry-label-col{background-color:color-mix(in oklab,var(--color-white) 10%,transparent)!important;border-color:color-mix(in oklab,var(--color-white) 10%,transparent)!important}'
            .'.dark .fi-in-entry.fi-in-entry-has-inline-label>.fi-in-entry-content-col{background-color:color-mix(in oklab,var(--color-white) 5%,transparent)!important;border-color:color-mix(in oklab,var(--color-white) 10%,transparent)!important}'
            .'.fi-fo-field.fi-fo-field-has-inline-label>.fi-fo-field-label-col{background-color:var(--gray-100)!important;border:1px solid var(--gray-200)!important;padding:.25rem .625rem!important}'
            .'.dark .fi-fo-field.fi-fo-field-has-inline-label>.fi-fo-field-label-col{background-color:color-mix(in oklab,var(--color-white) 10%,transparent)!important;border-color:color-mix(in oklab,var(--color-white) 10%,transparent)!important}'
            .'.fi-in-entry.fi-in-entry-has-inline-label,.fi-fo-field.fi-fo-field-has-inline-label{row-gap:.25rem!important;column-gap:.5rem!important}'
            .'.fi-section-content-ctn{padding:.25rem!important}'
            .'.fi-section-content{padding:.5rem!important}'
            .'.fi-section-header{padding:.5rem!important}'
            .'.fi-sc.fi-sc-has-gap{gap:.25rem!important}'
            .'.fi-sc.fi-sc-has-gap:has(>.fi-wi-widget,>.fi-loading-section){gap:1rem!important}'
            .'@media (min-width:640px){.fi-in-entry.fi-in-entry-has-inline-label,.fi-fo-field.fi-fo-field-has-inline-label{grid-template-columns:16rem minmax(0,1fr) minmax(0,1fr)!important}}'
            .'@media (min-width:1024px){.rb-column-flow>.fi-grid{grid-auto-flow:column!important;grid-template-rows:repeat(var(--rb-rows),auto)!important}}'
            .'.fi-in-entry.fi-in-entry-has-inline-label{align-items:stretch!important}'
            .'.fi-in-entry.fi-in-entry-has-inline-label .fi-in-entry-label-ctn{align-items:center!important}'
            .'</style>');
    }
}
