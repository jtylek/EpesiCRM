<?php

namespace Epesi\Modules\RecordBrowser\Recordset;

use Epesi\Modules\RecordBrowser\Filament\RelationManagers\LinkedRecordsRelationManager;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class IncomingLinks
{
    /**
     * @return array<class-string<RecordsetResource>, list<Field>>
     *
     * Empty for a model that isn't itself a recordset with a main-panel View
     * page — e.g. `App\Models\User`, whose Administration `ViewUser` page
     * shares this same `ViewRecord` base. A `Field::relation('user_id',
     * User::class)` on Contact still makes `User` the far side of a `Relation`
     * field, but "a tab for every recordset that links here" means the
     * viewed record has to be a recordset too.
     */
    public static function for(Model $target): array
    {
        if (LinkableRecordsets::resource($target->getMorphClass()) === null) {
            return [];
        }

        // Every addon tab of a View page asks again, and each answer walks every resource's fields.
        return app(FieldOverrides::class)->memo(
            'incoming:'.$target->getMorphClass().':'.$target->getKey().':'.Auth::id(),
            fn (): array => static::scan($target),
        );
    }

    /** @return array<class-string<RecordsetResource>, list<Field>> */
    protected static function scan(Model $target): array
    {
        $links = [];

        foreach (Filament::getPanel('main')->getResources() as $resource) {
            if (! is_subclass_of($resource, RecordsetResource::class) || ! $resource::canViewAny()) {
                continue;
            }

            foreach ($resource::resolvedFields() as $field) {
                if (! $field->isListedOnTarget()) {
                    continue;
                }

                $matches = match ($field->type) {
                    FieldType::Relation, FieldType::Relations => $field->getParam('model') === $target::class,
                    FieldType::Related => in_array($target->getMorphClass(), $field->relatedRecordsets(), true),
                    FieldType::Customer, FieldType::Customers => in_array($target::class, (array) $field->getParam('models', []), true),
                    default => false,
                };

                if ($matches) {
                    $links[$resource][] = $field;
                }
            }
        }

        uksort($links, fn (string $a, string $b): int => strnatcasecmp($a::getTitleCasePluralModelLabel(), $b::getTitleCasePluralModelLabel()));

        return $links;
    }

    /**
     * The tab's title for $resource's records linking to $target: the
     * recordset's plural name ("Tasks"), or "Linked from: Tasks" when they
     * are of $target's own recordset, so it isn't taken for $target's own
     * fields or a list of its own kind.
     *
     * @param  class-string<RecordsetResource>  $resource
     */
    public static function tabLabel(string $resource, Model $target): string
    {
        $label = $resource::getTitleCasePluralModelLabel();

        return $resource::getModel() === $target::class
            ? __('Linked from: :recordset', ['recordset' => $label])
            : $label;
    }

    public static function addons(Model $target): array
    {
        return array_map(
            fn (string $resource) => LinkedRecordsRelationManager::make(['sourceResource' => $resource]),
            array_keys(static::for($target)),
        );
    }

    /** @param list<Field> $fields */
    public static function query(string $resource, Model $target, array $fields): Builder
    {
        return $resource::getEloquentQuery()->where(function (Builder $query) use ($target, $fields): void {
            if ($fields === []) {
                $query->whereRaw('1 = 0');
            }

            foreach ($fields as $field) {
                $query->orWhere(function (Builder $query) use ($field, $target): void {
                    match ($field->type) {
                        FieldType::Relation => $query->where($query->qualifyColumn($field->name), $target->getKey()),
                        FieldType::Relations => $query->whereHas($field->getStateName(), fn (Builder $related) => $related->whereKey($target->getKey())),
                        FieldType::Related, FieldType::Customers => $query->whereHas('recordLinks', fn (Builder $links) => $links
                            ->where('field', $field->name)->where('target_type', $target->getMorphClass())->where('target_id', $target->getKey())),
                        FieldType::Customer => $query
                            ->where($query->qualifyColumn("{$field->name}_type"), $target->getMorphClass())
                            ->where($query->qualifyColumn("{$field->name}_id"), $target->getKey()),
                        default => $query->whereRaw('1 = 0'),
                    };
                });
            }
        });
    }
}
