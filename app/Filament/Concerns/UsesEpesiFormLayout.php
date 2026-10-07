<?php

namespace App\Filament\Concerns;

use Filament\Schemas\Schema;

/**
 * The Epesi form layout on a standalone page's form (Regional Settings, Mail
 * Server, the user settings pages, …): each label in a shaded box beside its
 * field rather than above it, styled by boxed-fields.css. Resource
 * Create/Edit/View pages get the same through BasePage::inlineLabels()
 * (AppServiceProvider); a plain Page builds its own form, so it opts in here.
 *
 * Filament calls defaultForm() on the fresh schema before the page's own
 * form(), so a page can still turn a section's labels back on top.
 */
trait UsesEpesiFormLayout
{
    public function defaultForm(Schema $schema): Schema
    {
        return $schema->inlineLabel();
    }
}
