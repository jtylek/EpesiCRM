<?php

namespace Epesi\Modules\Attachments;

/**
 * Public surface for other modules.
 */
class Attachments
{
    /**
     * Give another record type a Notes tab — the port of adding a row to
     * Epesi's "Attachments Related Recordsets" table. Call from a service
     * provider's register() or boot().
     */
    public static function enableFor(string $morphAlias): void
    {
        if (! in_array($morphAlias, AttachmentsServiceProvider::$recordTypes, true)) {
            AttachmentsServiceProvider::$recordTypes[] = $morphAlias;
        }
    }
}
