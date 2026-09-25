<?php

namespace Epesi\Modules\RecordBrowser\Recordset\Pages;

use App\Support\CloneRecordAction;
use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;

/**
 * The View page of a recordset: the record read-only, the addon tab strip
 * (module addons, then Record Info, then History) inherited from ViewRecord,
 * and the two record operations every recordset gets — Edit and Clone.
 *
 * Clone is on by default because duplicating a record is a recordset operation
 * in Epesi too, not a per-feature extra. A recordset that shouldn't offer it
 * sets `$recordsetCloneable = false`; one with unique columns that would collide
 * lists them in `$recordsetCloneResets` (email, say).
 */
abstract class ViewRecordset extends ViewRecord
{
    protected static bool $recordsetCloneable = true;

    /** @var array<int, string> columns to blank on the copy, beyond id/timestamps */
    protected static array $recordsetCloneResets = [];

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon(Heroicon::OutlinedPencil),
            ...(static::$recordsetCloneable ? [
                CloneRecordAction::make(static::getResource(), static::$recordsetCloneResets),
            ] : []),
        ];
    }
}
