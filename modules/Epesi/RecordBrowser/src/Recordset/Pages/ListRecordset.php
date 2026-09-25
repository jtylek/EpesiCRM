<?php

namespace Epesi\Modules\RecordBrowser\Recordset\Pages;

use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Filament\Actions\CreateAction;

/**
 * The List page of a recordset. A concrete recordset's List page is a five-line
 * stub extending this and naming its resource — see the class docblock on
 * RecordsetResource for why the file has to exist at all, and why nothing goes
 * in it.
 *
 * CreateAction hides itself when the resource has no Create page or the user
 * can't create, so this is safe as an unconditional default.
 */
abstract class ListRecordset extends ListRecords
{
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
