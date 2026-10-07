<?php

namespace Epesi\Modules\Mail;

use Epesi\Modules\RecordBrowser\Recordset\RecordsetFeatures;

/**
 * Public surface for other modules.
 */
class Mail
{
    /**
     * Let mail be filed under another record type, with an E-mails tab on it
     * — the port of adding a row to Epesi's `rc_related` table. Call from a
     * service provider's register() or boot(). Only the default: an
     * administrator can still turn it off for the type under Administration →
     * Recordsets.
     */
    public static function enableFor(string $morphAlias): void
    {
        RecordsetFeatures::enableByDefault(MailServiceProvider::FEATURE, $morphAlias);
    }
}
