<?php

namespace Epesi\Modules\RecordBrowser\Recordset\Pages;

use Epesi\Modules\RecordBrowser\Filament\Pages\EditRecord;

/**
 * The Edit page of a recordset — see CreateRecordset in this namespace; the
 * shared EditRecord base already carries the header-only Save/Cancel/Delete
 * convention and the redirect to View on save.
 */
abstract class EditRecordset extends EditRecord {}
