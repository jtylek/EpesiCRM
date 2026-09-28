<?php

namespace Epesi\Modules\RecordBrowser\Recordset;

use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Which collection fields a model has (Field::collection()), for
 * HasCollections: its relations, syncCollection() and whatever saves items
 * outside a form — an importer, the setup wizard.
 *
 * Read off the model's recordset, the resource built on the engine for it in
 * any panel, so the field list stays the one in the resource's fields(); the
 * administrator's collection fields come with it (resolvedFields()). A model
 * with no such resource has only the administrator's.
 *
 * Asked on every instantiation of such a model, so worked out once per model
 * per process, and forgotten with the custom-field definitions
 * (CustomFieldRegistry::refresh()/flush()), which can add one.
 */
class CollectionFields
{
    /** @var array<class-string<Model>, array<string, Field>> */
    protected static array $fields = [];

    /**
     * @param  class-string<Model>  $model
     * @return array<string, Field> field name => field, only those whose type is installed
     */
    public static function for(string $model): array
    {
        if (isset(static::$fields[$model])) {
            return static::$fields[$model];
        }

        $resource = static::resource($model, $settled);
        $fields = static::collectionsIn($resource !== null ? $resource::resolvedFields() : CustomFieldRegistry::fieldsFor($model));

        // Not remembered when the panels couldn't be asked yet: the next
        // instance, later in the request, asks again.
        if ($settled) {
            static::$fields[$model] = $fields;
        }

        return $fields;
    }

    /**
     * @param  class-string<Model>  $model
     */
    public static function field(string $model, string $name): ?Field
    {
        return static::for($model)[$name] ?? null;
    }

    public static function flush(): void
    {
        static::$fields = [];
    }

    /**
     * @param  array<int, Field>  $fields
     * @return array<string, Field>
     */
    protected static function collectionsIn(array $fields): array
    {
        $collections = [];

        foreach ($fields as $field) {
            if ($field->type === FieldType::Collection && $field->collectionType() !== null) {
                $collections[$field->name] = $field;
            }
        }

        return $collections;
    }

    /**
     * @param  class-string<Model>  $model
     * @param  bool  $settled  set to whether the panels could be asked at all
     * @return class-string<RecordsetResource>|null
     */
    protected static function resource(string $model, ?bool &$settled = null): ?string
    {
        $settled = false;

        try {
            $panels = Filament::getPanels();

            foreach ($panels as $panel) {
                $resource = $panel->getModelResource($model);

                if (is_string($resource) && is_subclass_of($resource, RecordsetResource::class)) {
                    $settled = true;

                    return $resource;
                }
            }

            $settled = $panels !== [];
        } catch (Throwable) {
            // No panels yet, early in boot: the administrator's alone for now.
        }

        return null;
    }
}
