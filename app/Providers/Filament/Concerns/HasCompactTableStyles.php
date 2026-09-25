<?php

namespace App\Providers\Filament\Concerns;

use Illuminate\Support\HtmlString;

/**
 * Shared panel-wide table/header/sidebar density override, used by every
 * panel provider so tables and navigation stay visually consistent across
 * panels (e.g. the "administration" panel's Login Audit table) instead of
 * falling back to Filament's default, noticeably taller spacing.
 */
trait HasCompactTableStyles
{
    protected function compactTableStyles(): HtmlString
    {
        $headedTable = '.fi-ta-header-ctn:has(>.fi-ta-header):not(:has(>.fi-ta-filters-above-content-ctn))';

        return new HtmlString('<style>'
            .'.fi-main{padding-inline-start:10px!important;padding-inline-end:10px!important}'
            .'.fi-ta-text:not(.fi-inline),.fi-ta-icon,.fi-ta-color,.fi-ta-image,.fi-ta-select,.fi-ta-toggle,.fi-ta-text-input,.fi-ta-range-summary,.fi-ta-text-summary,.fi-ta-icon-count-summary,.fi-ta-values-summary,.fi-ta-cell.fi-ta-selection-cell,.fi-ta-cell:has(.fi-ta-actions),.fi-ta-cell:has(.fi-ta-record-checkbox){padding-block:.375rem!important}'
            // Row checkbox + action icons: Filament starts them 1.5rem in from the
            // card edge with .75rem either side and a .75rem gap between icons,
            // which spends ~40px of every row on whitespace. A .375rem gap is the
            // floor: a row icon button's hit area overhangs its icon by .375rem
            // (size-8/-m-1.5), so any tighter and it would cover the next icon.
            // Rows of labelled link/button actions keep Filament's wider gap.
            // Scoped to `.fi-ta-cell>` because the table toolbar and header
            // actions reuse the `.fi-ta-actions` class.
            .'.fi-ta-cell.fi-ta-selection-cell,.fi-ta-cell.fi-ta-group-selection-cell,.fi-ta-cell:has(>.fi-ta-record-checkbox),.fi-ta-cell:has(>.fi-ta-actions){padding-inline:.25rem!important}'
            .'.fi-ta-cell.fi-ta-selection-cell:first-of-type,.fi-ta-cell.fi-ta-group-selection-cell:first-of-type,.fi-ta-cell:has(>.fi-ta-record-checkbox):first-of-type,.fi-ta-cell:has(>.fi-ta-actions):first-of-type{padding-inline-start:.75rem!important}'
            .'.fi-ta-cell.fi-ta-selection-cell:last-of-type,.fi-ta-cell.fi-ta-group-selection-cell:last-of-type,.fi-ta-cell:has(>.fi-ta-record-checkbox):last-of-type,.fi-ta-cell:has(>.fi-ta-actions):last-of-type{padding-inline-end:.75rem!important}'
            .'.fi-ta-cell>.fi-ta-actions{gap:.375rem!important}'
            .'.fi-ta-cell>.fi-ta-actions:has(.fi-link,.fi-btn){gap:.75rem!important}'
            .'.fi-ta-header-cell{padding-block:.5rem!important}'
            .'.fi-ta-header{padding-block:.625rem!important;padding-inline:1rem!important}'
            // A table with a heading (every addon, and pages such as Watched
            // records) keeps its search box, filters and column toggle on the
            // heading's row instead of a toolbar row of their own below it; too
            // narrow for both, the toolbar wraps under the heading, right-aligned
            // as before. The side padding and bottom border (Filament's colours)
            // move to the row, so a wrapped toolbar keeps its gutter. The
            // toolbar's .25rem padding brings a 2.25rem search box level with the
            // heading, and its left-hand actions slot (bulk actions, reorder,
            // grouping) takes no room while it's empty. A table with filters
            // above its content keeps Filament's layout.
            .$headedTable.'{display:flex;flex-wrap:wrap;column-gap:1rem;padding-inline:1rem;border-bottom:1px solid var(--gray-200)}'
            .'.dark '.$headedTable.'{border-bottom-color:#ffffff1a}'
            .$headedTable.'>.fi-ta-header{flex:1 1 auto;justify-content:center;padding-inline:0!important;border-bottom:0!important}'
            .$headedTable.'>.fi-ta-header-toolbar{flex:0 1 auto;margin-inline-start:auto;padding-block:.25rem!important;padding-inline:0!important;border-bottom:0!important}'
            .$headedTable.'>.fi-ta-header-toolbar>.fi-ta-actions:not(:has(*)){display:none}'
            .'.fi-header .fi-breadcrumbs{margin-bottom:0!important;padding-block-start:1rem!important}'
            // Bottom: the same 10px as the side gutter rather than Filament's 2rem,
            // which on a page that fills the window (Mailbox) forces a scrollbar.
            .'.fi-page-header-main-ctn{row-gap:.5rem!important;padding-block:0 10px!important}'
            .'.fi-header-has-breadcrumbs .fi-header-actions-ctn{margin-top:.5rem!important}'
            // A View page's addon tab strip sits .75rem from the infolist above
            // (.5rem on top of the .25rem schema gap) and from the addon below,
            // instead of the gap's bare .25rem above and Filament's 1.5rem below.
            .'.epesi-addon-tabs{margin-top:.5rem!important}'
            .'.epesi-addon-tabs>.fi-sc-tabs-tab.fi-active{margin-top:.75rem!important}'
            // Right side: Filament's 1.5rem padding plus a scrollbar gutter kept
            // open even with nothing to scroll left ~3rem between the menu and
            // the page. .5rem cancels the nav groups' -.5rem margin, so an item
            // ends at the sidebar's edge and the page's 10px gutter is the gap.
            .'.fi-sidebar-nav{padding-block-start:.75rem!important;padding-inline-end:.5rem!important;scrollbar-gutter:auto!important}'
            .'.fi-sidebar-group-items{row-gap:0!important}'
            .'.fi-sidebar-item-btn{padding-block:.375rem!important}'
            .'</style>');
    }
}
