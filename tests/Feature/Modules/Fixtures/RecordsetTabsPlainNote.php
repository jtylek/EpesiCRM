<?php

namespace Tests\Feature\Modules;

use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Illuminate\Database\Eloquent\Model;

/** A recordset whose model doesn't soft-delete, for the Records tab's plain list. */
class RecordsetTabsPlainNote extends Model
{
    protected $table = 'recordset_tabs_plain_notes';

    protected $guarded = [];
}

class RecordsetTabsPlainNoteResource extends RecordsetResource
{
    protected static ?string $model = RecordsetTabsPlainNote::class;

    public static function fields(): array
    {
        return [
            Field::text('name')->inTable()->searchable(),
        ];
    }

    /** No pages: registered on the main panel only for the test, after its routes. */
    public static function getPages(): array
    {
        return [];
    }
}
