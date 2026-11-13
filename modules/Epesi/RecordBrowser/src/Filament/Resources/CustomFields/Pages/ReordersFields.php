<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages;

use App\Support\Demo;
use Epesi\Modules\RecordBrowser\Recordset\FieldOverrides;

/**
 * What FieldCatalogue asks of a page that shows it: the recordset the list is
 * on and whether, and how, its fields are reordered. Needs a `$recordset`
 * property (null for the list of every recordset) and the table concerns.
 */
trait ReordersFields
{
    /** The recordset the list is on: the one it was opened from, or the filter's choice. */
    public function selectedRecordset(): ?string
    {
        return $this->recordset ?? ($this->tableFilters['model_type']['value'] ?? null);
    }

    public function canReorderFields(): bool
    {
        $alias = $this->selectedRecordset();

        return ! Demo::enabled() && filled($alias)
            && blank($this->tableFilters['origin']['value'] ?? null)
            && blank($this->getTableSearch())
            && app(FieldOverrides::class)->canReorder($alias);
    }

    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        app(FieldOverrides::class)->authorize();
        abort_unless($this->canReorderFields() && $this->isTableReordering(), 403);
        app(FieldOverrides::class)->reorder($this->selectedRecordset(), $order);
        $this->flushCachedTableRecords();
    }
}
