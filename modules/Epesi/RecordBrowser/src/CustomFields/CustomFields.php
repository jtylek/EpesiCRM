<?php

namespace Epesi\Modules\RecordBrowser\CustomFields;

use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Database\Eloquent\Model;

/**
 * Custom fields for a **hand-written** Filament resource — one appended line per
 * schema:
 *
 *     // ContactForm::configure()
 *     Section::make()->components([ ...existing... ]),
 *     ...CustomFields::formSections(Contact::class),
 *
 * A resource built on RecordsetResource needs none of this: the engine
 * concatenates the module's declared fields with the administrator's before
 * building anything, so both halves already run through one code path. This is
 * the route for the resources that predate the engine, and the permanent escape
 * hatch for any that stay hand-written on purpose.
 *
 * @see RecordsetResource
 */
class CustomFields
{
    /**
     * Where custom fields with no section of their own land on a hand-written
     * screen. They can't join the resource's existing card — that card is built
     * by the resource — so they get one of their own, named the way Epesi's own
     * additional-fields page split is.
     */
    public const DEFAULT_SECTION = 'Additional Information';

    /**
     * @param  class-string<Model>  $model
     */
    public static function has(string $model): bool
    {
        return CustomFieldRegistry::forModel($model) !== [];
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<int, Section>
     */
    public static function formSections(string $model): array
    {
        return static::sections(
            static::fields($model, fn (Field $field): bool => $field->isInForm()),
            fn (Field $field): mixed => $field->toFormComponent(),
        );
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<int, Section>
     */
    public static function infolistSections(string $model): array
    {
        return static::sections(
            static::fields($model, fn (Field $field): bool => $field->isInView()),
            fn (Field $field): mixed => $field->toInfolistEntry(),
        );
    }

    /**
     * Appended to a hand-written table's ->columns([...]); each is toggleable
     * and hidden by default unless the definition says "show in table".
     *
     * @param  class-string<Model>  $model
     * @return array<int, Column>
     */
    public static function tableColumns(string $model): array
    {
        return array_map(
            fn (Field $field): Column => $field->toTableColumn(),
            static::fields($model, fn (Field $field): bool => $field->isInColumnChooser()),
        );
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<int, BaseFilter>
     */
    public static function tableFilters(string $model): array
    {
        return array_values(array_filter(array_map(
            fn (Field $field): ?BaseFilter => $field->toTableFilter(),
            static::fields($model),
        )));
    }

    /**
     * @param  class-string<Model>  $model
     * @param  null|callable(Field): bool  $filter
     * @return array<int, Field>
     */
    protected static function fields(string $model, ?callable $filter = null): array
    {
        $fields = CustomFieldRegistry::fieldsFor($model);

        return $filter === null ? $fields : array_values(array_filter($fields, $filter));
    }

    /**
     * @param  array<int, Field>  $fields
     * @param  callable(Field): mixed  $build
     * @return array<int, Section>
     */
    protected static function sections(array $fields, callable $build): array
    {
        $grouped = [];

        foreach ($fields as $field) {
            $grouped[$field->getSection() ?? static::DEFAULT_SECTION][] = $build($field);
        }

        $sections = [];

        foreach ($grouped as $title => $components) {
            $sections[] = Section::make(filled($title) ? __($title) : null)
                ->columnSpanFull()
                ->columns(2)
                ->components($components);
        }

        return $sections;
    }
}
