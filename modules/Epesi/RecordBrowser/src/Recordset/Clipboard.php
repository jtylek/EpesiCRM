<?php

namespace Epesi\Modules\RecordBrowser\Recordset;

/**
 * Public surface for a module that wants its recordset to offer "Copy to
 * clipboard" by default — the port of Legacy's set_clipboard_pattern(),
 * called once from a service provider's boot(), as Attachments::enableFor()
 * is for Notes.
 *
 * This sets two things at once: the `clipboard` RecordsetFeatures default
 * (so the icon shows on the View page until an administrator decides
 * otherwise) and the module's own default pattern text, which is distinct
 * from an administrator's stored override (ClipboardPattern::storedPatternFor(),
 * the `epesi_recordbrowser_clipboard_patterns` table). An administrator who
 * clears their override falls back to this text, not to nothing.
 */
class Clipboard
{
    /** @var array<string, string> morph alias => the module's default pattern */
    protected static array $defaults = [];

    /**
     * @param  string  $alias  the recordset's morph alias ("company")
     * @param  string  $pattern  the module's default pattern text (see AI-shared/recordbrowser/clipboard.md)
     */
    public static function enableFor(string $alias, string $pattern): void
    {
        RecordsetFeatures::enableByDefault('clipboard', $alias);
        static::$defaults[$alias] = $pattern;
    }

    /** The module's default pattern for $alias, or null when none declared one. */
    public static function defaultPattern(string $alias): ?string
    {
        return static::$defaults[$alias] ?? null;
    }

    /** For tests: forget every module default. */
    public static function reset(): void
    {
        static::$defaults = [];
    }
}
