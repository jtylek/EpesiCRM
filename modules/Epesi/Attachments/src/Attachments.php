<?php

namespace Epesi\Modules\Attachments;

use Epesi\Modules\RecordBrowser\Recordset\RecordsetFeatures;

/**
 * Public surface for other modules.
 */
class Attachments
{
    /**
     * Give another record type a Notes tab — the port of adding a row to
     * Epesi's "Attachments Related Recordsets" table. Call from a service
     * provider's register() or boot(). Only the default: an administrator can
     * still turn Notes off for it under Administration → Recordsets.
     */
    public static function enableFor(string $morphAlias): void
    {
        RecordsetFeatures::enableByDefault(AttachmentsServiceProvider::FEATURE, $morphAlias);
    }
}
