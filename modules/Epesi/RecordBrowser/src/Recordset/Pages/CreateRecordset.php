<?php

namespace Epesi\Modules\RecordBrowser\Recordset\Pages;

use Epesi\Modules\RecordBrowser\Filament\Pages\CreateRecord;

/**
 * The Create page of a recordset. Everything it does — the form built from
 * `fields()`, the header-only Create/Cancel actions, the inline-label layout —
 * comes from the engine and the shared CreateRecord base, so the concrete stub
 * that extends this holds only its resource name.
 */
abstract class CreateRecordset extends CreateRecord {}
