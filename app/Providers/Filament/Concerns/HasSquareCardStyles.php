<?php

namespace App\Providers\Filament\Concerns;

use Illuminate\Support\HtmlString;

/**
 * Square corners on every card-like container: sections (a record's fields,
 * dashboard widgets), tables and the History/Notes tab bar, fieldsets, the
 * login box. Controls keep theirs — buttons, badges, inputs, tab items, the
 * boxed field rows, modals and dropdowns stay rounded.
 *
 * The selectors are the ones Filament's own stylesheet rounds with
 * `--radius-xl`, so a container Filament adds later stays rounded until it is
 * listed here, rather than everything turning square.
 */
trait HasSquareCardStyles
{
    protected function squareCardStyles(): HtmlString
    {
        return new HtmlString('<style>'
            .'.fi-section:not(.fi-section-not-contained),'
            .'.fi-section:not(.fi-section-not-contained)>.fi-section-content-ctn,'
            .'.fi-ta-ctn,.fi-ta-content-ctn,'
            .'.fi-ta-ctn .fi-ta-filters-before-content-ctn,.fi-ta-ctn .fi-ta-filters-after-content-ctn,'
            .'.fi-ta-content-ctn .fi-ta-content.fi-ta-content-grid .fi-ta-record,'
            .'.fi-tabs,.fi-sc-tabs.fi-contained,.fi-sc-wizard.fi-contained,'
            .'.fi-fieldset,.fi-callout,.fi-empty-state,.fi-wi-stats-overview-stat,'
            .'.fi-in-repeatable.fi-contained>.fi-in-repeatable-item,'
            .'.fi-fo-repeater .fi-fo-repeater-item,.fi-fo-builder .fi-fo-builder-item,'
            .'.fi-simple-main'
            .'{border-radius:0!important}'
            .'</style>');
    }
}
