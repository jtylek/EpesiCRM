<?php

namespace Epesi\Modules\RecordBrowser\Filament\Pages;

use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use Epesi\Modules\RecordBrowser\Browsing\RecentRecords;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord as BaseEditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Shared base for every resource's Edit page — extend this instead of
 * Filament's own EditRecord. Bakes in three panel-wide conventions so a new
 * resource gets them for free instead of repeating ~30 lines of boilerplate:
 * Save/Cancel/Delete consolidated into the header (icons included, no
 * duplicate action row at the bottom of the form), and Save redirecting to
 * the record's View page instead of back to Edit.
 *
 * There's no Filament-provided global hook for page-level behaviour the way
 * Table::configureUsing()/TextInput::configureUsing() work for those
 * components — Pages sit on a Livewire-based class hierarchy, not the
 * Filament\Support\Components\Component chain those use — so a shared base
 * class is the closest equivalent: one `extends` per resource instead of
 * zero, but no per-method duplication.
 */
abstract class EditRecord extends BaseEditRecord
{
    use HasResourceIconBreadcrumb;
    use HidesPageHeading;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        RecentRecords::opened(static::getResource(), $this->getRecord());
    }

    /**
     * Matches the View page's compact "label beside value" layout (see
     * each resource's Infolist) instead of Filament's default label-above-
     * field. Overrides the instance method rather than calling the
     * inherited static `inlineLabels()` setter: `Filament\Pages\BasePage`
     * declares `$hasInlineLabels` once and no resource page subclass
     * (Create/Edit/View) redeclares it, so PHP has every one of them share
     * the same storage — calling the setter on any single page class would
     * flip it panel-wide, turning on inline labels for Create too. This
     * stays scoped to Edit only.
     */
    public function hasInlineLabels(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            // getSaveFormAction() renders type="submit"; moved here (outside
            // the <form>), it needs formId('form') or the button has no
            // owning form and clicking it is a silent no-op.
            $this->getSaveFormAction()->label('Save')->icon(Heroicon::OutlinedCheck)->color('success')->formId('form'),
            ViewAction::make()->label('Cancel')->color('gray')->icon(Heroicon::OutlinedXMark),
            DeleteAction::make()->icon(Heroicon::OutlinedTrash),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * All actions live in the header (see above) — no duplicate Save/Cancel
     * pair at the bottom of the form.
     */
    protected function getFormActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    /**
     * Vendor default appends getRelationManagersContentComponent() (History,
     * etc.) below the form on every Edit page. History is a record of past
     * changes — relevant when reviewing a record (View page's own tab strip,
     * see ViewRecord.php), not while mid-edit — so it's dropped here instead.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
        ]);
    }
}
