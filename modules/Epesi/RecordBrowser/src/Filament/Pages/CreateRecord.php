<?php

namespace Epesi\Modules\RecordBrowser\Filament\Pages;

use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use Filament\Resources\Pages\CreateRecord as BaseCreateRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Shared base for every resource's Create page — see EditRecord in this
 * namespace for why this exists (the same header-action-consolidation
 * convention, just no redirect override: Filament's default post-create
 * redirect is left as-is here since no one's asked to change it yet).
 */
abstract class CreateRecord extends BaseCreateRecord
{
    use HasResourceIconBreadcrumb;
    use HidesPageHeading;

    /**
     * Matches View/Edit's compact "label beside value" layout — see
     * EditRecord::hasInlineLabels() in this namespace for why this
     * overrides the instance method rather than calling the inherited
     * static `inlineLabels()` setter (it would flip the flag panel-wide
     * instead of scoping to just this page type).
     */
    public function hasInlineLabels(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            // getCreateFormAction() renders type="submit"; moved here
            // (outside the <form>), it needs formId('form') or the button
            // has no owning form and clicking it is a silent no-op.
            $this->getCreateFormAction()->label('Create')->icon(Heroicon::OutlinedCheck)->color('success')->formId('form'),
            ...($this->canCreateAnother() ? [
                $this->getCreateAnotherFormAction()->icon(Heroicon::OutlinedPlus),
            ] : []),
            $this->getCancelFormAction()->icon(Heroicon::OutlinedXMark),
        ];
    }

    /**
     * All actions live in the header (see above) — no duplicate action row
     * at the bottom of the form.
     */
    protected function getFormActions(): array
    {
        return [];
    }
}
