<?php

namespace App\Filament\Dashboard;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * What an applet row shows on hover: the record's description, as plain text,
 * so the row itself can stay short enough for a phone. Anything else worth
 * knowing (a deadline, when a call is due) belongs in the row, not in a
 * labelled list here. Give it to a column's tooltip(), or to x-tooltip with
 * allowHTML.
 */
final class AppletTooltip
{
    /** Cut to 500 characters, escaped, line breaks kept. Null when blank, so there is no tooltip. */
    public static function text(?string $value): ?HtmlString
    {
        $value = trim((string) $value);

        return $value === '' ? null : new HtmlString(nl2br(e(Str::limit($value, 500))));
    }
}
