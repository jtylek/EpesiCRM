<?php

namespace Epesi\Modules\RecordBrowser\Recordset;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Throwable;

/**
 * What a "link to any record" field (Field::related()) can point at: every
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

    protected static function panel(): ?Panel
    {
        try {
            return Filament::getPanel('main');
        } catch (Throwable) {
            return null;
        }
    }
}
