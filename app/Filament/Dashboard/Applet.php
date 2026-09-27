<?php

namespace App\Filament\Dashboard;

use Filament\Schemas\Components\Component;

/**
 * A panel widget the user can put on the Dashboard: Epesi's applet, a module
 * with applet_caption(), applet_info() and applet_settings(). A user may add
 * it more than once, each copy with its own settings, and a widget that
 * doesn't implement this never shows on the Dashboard. IsApplet implements
 * all of it except the caption.
 */
interface Applet
{
    /** applet_caption(): its name in "Add applet" and on its settings form. */
    public static function getAppletCaption(): string;

    /** applet_info(): a line under the caption in "Add applet". */
    public static function getAppletDescription(): ?string;

    /**
     * applet_settings(): the fields of its settings form, each named after
     * the setting it holds.
     *
     * @return array<Component>
     */
    public static function getAppletSettingsSchema(): array;

    /**
     * Each setting's value until the user saves one.
     *
     * @return array<string, mixed>
     */
    public static function getAppletSettingsDefaults(): array;
}
