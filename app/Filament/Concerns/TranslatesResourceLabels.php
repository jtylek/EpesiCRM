<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * A resource's model label, plural, navigation label and breadcrumb through
 * __(). Filament reads these from static properties, which can't call a
 * function, so they are translated on the way out instead. The plural is
 * formed in English first ("phone call" → "phone calls") and translated as a
 * whole, since pluralising a translation only works in English.
 */
trait TranslatesResourceLabels
{
    public static function getModelLabel(): string
    {
        return __(parent::getModelLabel());
    }

    public static function getPluralModelLabel(): string
    {
        return __(static::$pluralModelLabel ?? Str::plural(parent::getModelLabel()));
    }

    public static function getTitleCaseModelLabel(): string
    {
        return static::ownTitleCase(parent::getModelLabel()) ?? static::titleCase(static::getModelLabel());
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return static::ownTitleCase(static::$pluralModelLabel ?? Str::plural(parent::getModelLabel()))
            ?? static::titleCase(static::getPluralModelLabel());
    }

    /**
     * The translation of "Companies" rather than "companies" capitalised,
     * where there is one: that is the string the sidebar reads as, so it is
     * the one an administrator changes on the Translations page.
     */
    protected static function ownTitleCase(string $english): ?string
    {
        $key = Str::ucwords($english);

        return Lang::hasForLocale($key) ? __($key) : null;
    }

    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel !== null ? __(static::$navigationLabel) : static::getTitleCasePluralModelLabel();
    }

    /**
     * "Phone Calls" in English; only the first letter elsewhere ("Połączenia
     * telefoniczne"), since title case is an English habit.
     */
    protected static function titleCase(string $label): string
    {
        return app()->getLocale() === 'en' ? Str::ucwords($label) : Str::ucfirst($label);
    }
}
