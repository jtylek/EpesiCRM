<?php

namespace Epesi\Modules\Notes\Filament\Resources\Notes\Pages;

use Epesi\Modules\Notes\Filament\Resources\Notes\NoteResource;
use Epesi\Modules\RecordBrowser\Recordset\Pages\ListRecordset;

/** Stub — all behaviour is in the engine. See NoteResource::fields(). */
class ListNotes extends ListRecordset
{
    protected static string $resource = NoteResource::class;
}
