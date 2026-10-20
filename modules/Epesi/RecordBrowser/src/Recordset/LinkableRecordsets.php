<?php

namespace Epesi\Modules\RecordBrowser\Recordset;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Support\Enums\IconSize;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Throwable;

use function Filament\Support\generate_icon_html;

/**
 * What a Linked to field (Field::linkedTo()) can point at: every
 * model in the morph map with a View page in the main panel — Epesi's
 * `__RECORDSETS__`. Derived rather than configured, as
 * CustomFieldRegistry::participatingModels() is, so a module's recordset is
 * offered as soon as it's installed. A field narrows it with its own list.
 *
 * Always the main panel's resources, even from Administration → Fields: the
 * records being linked live there.
 */
class LinkableRecordsets
{
    /**
     * @return array<string, string> morph alias => label, by label
     */
    public static function options(): array
    {
        $options = [];

        foreach (array_keys(Relation::morphMap()) as $alias) {
            if (static::resource($alias) !== null) {
                $options[$alias] = static::label($alias);
            }
        }

        asort($options);

        return $options;
    }

    /**
     * @return class-string<\Filament\Resources\Resource>|null
     */
    public static function resource(string $alias): ?string
    {
        $class = Relation::getMorphedModel($alias);

        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        $resource = static::panel()?->getModelResource($class);

        return $resource && $resource::hasPage('view') ? $resource : null;
    }

    /** "Company", "Phone call" — the resource's own (translated) name for one record. */
    public static function label(string $alias): string
    {
        $resource = static::resource($alias);

        return $resource ? Str::ucfirst($resource::getModelLabel()) : Str::headline($alias);
    }

    /**
     * A record's title as its recordset names it ("Acme Ltd"), from the main
     * panel's resource whichever panel is current — or "Company #12" for one
     * without a resource.
     */
    public static function title(Model $record): string
    {
        $resource = static::resource($record->getMorphClass());
        $title = $resource ? $resource::getRecordTitle($record) : null;

        return filled($title) ? strip_tags((string) $title) : Str::headline($record->getMorphClass()).' #'.$record->getKey();
    }

    /**
     * The recordset's icon, the one beside it in the sidebar — so a contact
     * reads differently from a company at a glance wherever records of several
     * recordsets are offered side by side.
     */
    public static function icon(string $alias): string|BackedEnum|Htmlable|null
    {
        $resource = static::resource($alias);

        return $resource ? $resource::getNavigationIcon() : null;
    }

    /**
     * $label (escaped) after the recordset's icon, as HTML for a select that
     * allows it (allowHtml()).
     */
    public static function iconLabel(string $alias, string $label): string
    {
        $icon = generate_icon_html(static::icon($alias), size: IconSize::Small)?->toHtml();

        return ($icon
            ? '<span style="display: inline-block; vertical-align: middle; margin-right: 0.375rem; line-height: 1;">'.$icon.'</span>'
            : '').e($label);
    }

    protected static function panel(): ?Panel
    {
        try {
            return Filament::getPanel('main');
        } catch (Throwable) {
            return null;
        }
    }
}
