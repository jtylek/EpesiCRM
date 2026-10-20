<?php

namespace Epesi\Modules\RecordBrowser\Recordset;

use App\Support\Demo;
use Closure;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\Models\CollectionItem;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FieldOverrides
{
    public const TABLE = 'epesi_recordbrowser_field_overrides';

    protected ?array $overrides = null;

    protected array $columns = [];

    /** @var array<string, mixed> Per-request answers built from the resolved fields; see memo(). */
    protected array $memo = [];

    protected int $memoVersion = -1;

    /** Only request-local caching: settings never depend on a deployment cache file. */
    public function all(): array
    {
        if ($this->overrides !== null) {
            return $this->overrides;
        }

        // Asked straight away rather than through Schema::hasTable(), an
        // information_schema query on MySQL on every page: the table is only
        // missing before the update that adds it.
        try {
            $rows = DB::table(self::TABLE)->get();
        } catch (QueryException) {
            return [];
        }

        $this->overrides = [];
        foreach ($rows as $row) {
            $this->overrides[$row->model_type][$row->field] = json_decode($row->properties, true, flags: JSON_THROW_ON_ERROR);
        }

        return $this->overrides;
    }

    /**
     * Keep what is derived from the resolved fields for the rest of the request:
     * `resolvedFields()` and the incoming-links scan call every resource's
     * `fields()`, and a View page asks for them once per addon tab. Dropped when
     * an override is saved or the custom fields change (their version moves).
     */
    public function memo(string $key, Closure $build): mixed
    {
        if ($this->memoVersion !== CustomFieldRegistry::version()) {
            $this->memo = [];
            $this->memoVersion = CustomFieldRegistry::version();
        }

        return array_key_exists($key, $this->memo) ? $this->memo[$key] : $this->memo[$key] = $build();
    }

    public function properties(string $alias, string $name): array
    {
        return $this->all()[$alias][$name] ?? [];
    }

    /** @return array<string, array{label: string, model: class-string, fields: array}> */
    public function recordsets(): array
    {
        $recordsets = [];
        foreach (Filament::getPanel('main')->getResources() as $resource) {
            if (! is_subclass_of($resource, RecordsetResource::class)) {
                continue;
            }
            $model = $resource::getModel();
            if ($alias = CustomFieldRegistry::aliasFor($model)) {
                $recordsets[$alias] = ['label' => $resource::getPluralModelLabel(), 'model' => $model, 'fields' => $resource::fields()];
            }
        }
        foreach (Relation::morphMap() as $alias => $model) {
            if (is_subclass_of($model, CollectionItem::class)) {
                $recordsets[$alias] = ['label' => __(CustomFieldRegistry::participatingModels()[$alias] ?? $alias), 'model' => $model, 'fields' => $model::fields()];
            }
        }

        return $recordsets;
    }

    public function definition(string $alias, string $name): array
    {
        $recordset = $this->recordsets()[$alias] ?? null;
        abort_unless($recordset, 404);
        foreach ($recordset['fields'] as $position => $field) {
            if ($field->name === $name) {
                return [$recordset['model'], $this->withOffers($alias, $field), $position * 10];
            }
        }
        abort(404);
    }

    public function editable(string $model, Field $field): array
    {
        $properties = $field->administratorEditableProperties();
        if (is_subclass_of($model, CollectionItem::class)) {
            // Item summaries are authored by their type, not built as an infolist.
            $properties = array_diff($properties, ['section', 'show_in_view']);
            if (! $field->type->isTextual() && ! in_array($field->type, [FieldType::LongText, FieldType::Select, FieldType::Multiselect, FieldType::CommonData], true)) {
                $properties = array_diff($properties, ['filterable']);
            }
        }
        if ($field->type->hasColumn()) {
            if (! isset($this->columns[$model])) {
                // Model initialization resolves its collection fields through this service.
                $this->columns[$model] = [];
                $instance = new $model;
                $this->columns[$model] = $instance->getConnection()->getSchemaBuilder()->getColumns($instance->getTable());
            }
            $column = collect($this->columns[$model])->firstWhere('name', $field->name);
            if ($column && ! $column['nullable'] && $column['default'] === null) {
                $properties = array_diff($properties, ['required', 'show_in_form']);
            }
        }

        return array_values($properties);
    }

    public function save(string $alias, string $name, array $properties): void
    {
        $this->authorize();
        [$model, $field] = $this->definition($alias, $name);
        $allowed = $this->editable($model, $field);
        if (array_diff(array_keys($properties), $allowed)) {
            throw ValidationException::withMessages(['properties' => __('These field properties are protected by the module.')]);
        }
        Validator::make($properties, [
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'help' => ['nullable', 'string', 'max:4000'],
            'section' => ['nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'show_in_form' => ['sometimes', 'boolean'],
            'show_in_view' => ['sometimes', 'boolean'],
            'show_in_table' => ['sometimes', 'boolean'],
            'required' => ['sometimes', 'boolean'],
            'filterable' => ['sometimes', 'boolean'],
            // A Linked to field's recordsets: LinkedTo::ANY, or some of the linkable ones.
            'recordsets' => ['sometimes', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === LinkedTo::ANY) {
                    return;
                }

                if (! is_array($value) || $value === [] || array_diff($value, array_keys(LinkableRecordsets::options())) !== []) {
                    $fail(__('Choose at least one recordset it can link to, or any recordset.'));
                }
            }],
        ])->validate();

        $effective = array_replace($field->administratorDefaults(), $properties);
        if ($effective['required'] && ! $effective['show_in_form'] && ($field->isInForm() || ! $field->isRequired())) {
            throw ValidationException::withMessages(['required' => __('A required field must remain on the form.')]);
        }

        foreach (['required', 'show_in_form', 'show_in_view', 'show_in_table', 'filterable'] as $key) {
            if (array_key_exists($key, $properties)) {
                $properties[$key] = (bool) $properties[$key];
            }
        }
        if (isset($properties['position'])) {
            $properties['position'] = (int) $properties['position'];
        }
        if (is_array($properties['recordsets'] ?? null)) {
            $properties['recordsets'] = array_values(array_unique($properties['recordsets']));
        }
        if ($properties === []) {
            DB::table(self::TABLE)->where('model_type', $alias)->where('field', $name)->delete();
        } else {
            DB::table(self::TABLE)->upsert([
                'model_type' => $alias, 'field' => $name,
                'properties' => json_encode($properties, JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ], ['model_type', 'field'], ['properties', 'updated_at']);
        }
        $this->overrides = null;
        $this->memo = [];
        CollectionFields::flush();
    }

    public function authorize(): void
    {
        abort_unless(auth()->user()?->active && auth()->user()?->hasRole('super_admin'), 403);
        abort_if(Demo::enabled(), 403);
    }

    public function canReorder(string $alias): bool
    {
        $recordset = $this->recordsets()[$alias] ?? null;

        return $recordset !== null && collect($recordset['fields'])->every(
            fn (Field $field): bool => in_array('position', $field->administratorEditableProperties(), true),
        );
    }

    /** Persist one complete recordset order, including custom fields, without replacing other settings. */
    public function reorder(string $alias, array $order): void
    {
        $this->authorize();
        abort_unless($this->canReorder($alias), 403);
        $fields = [];
        foreach ($this->recordsets()[$alias]['fields'] as $field) {
            $fields["module:{$alias}:{$field->name}"] = $field->name;
        }
        $custom = CustomField::query()->where('model_type', $alias)->get()->keyBy('column');
        foreach ($custom as $field) {
            $fields["custom:{$field->id}"] = $field->column;
        }
        Validator::make(['order' => $order], [
            'order' => ['required', 'array', 'size:'.count($fields)],
            'order.*' => ['required', 'string', 'distinct', Rule::in(array_keys($fields))],
        ])->validate();

        DB::transaction(function () use ($alias, $fields, $order, $custom): void {
            $existing = DB::table(self::TABLE)->where('model_type', $alias)->lockForUpdate()->get()->keyBy('field');
            $rows = [];
            foreach (array_values($order) as $position => $key) {
                $name = $fields[$key];
                $properties = isset($existing[$name]) ? json_decode($existing[$name]->properties, true, flags: JSON_THROW_ON_ERROR) : [];
                $properties['position'] = $position * 10;
                if (isset($custom[$name])) {
                    CustomField::query()->whereKey($custom[$name]->id)->update(['position' => $position * 10]);
                }
                $rows[] = [
                    'model_type' => $alias, 'field' => $name,
                    'properties' => json_encode($properties, JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
            DB::table(self::TABLE)->upsert($rows, ['model_type', 'field'], ['properties', 'updated_at']);
        });
        $this->overrides = null;
        $this->memo = [];
        CustomFieldRegistry::refresh();
    }

    /** Keep the custom-field editor's numeric Position consistent with a dragged position. */
    public function syncCustomPosition(CustomField $field): void
    {
        $properties = $this->properties($field->model_type, $field->column);
        if (! array_key_exists('position', $properties)) {
            return;
        }
        $properties['position'] = (int) $field->position;
        DB::table(self::TABLE)->where('model_type', $field->model_type)->where('field', $field->column)
            ->update(['properties' => json_encode($properties, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
        $this->overrides = null;
        $this->memo = [];
        CollectionFields::flush();
    }

    /**
     * A Linked to field with what other modules offer it (LinkedTo::offer())
     * joined to the module's own list: the default an administrator's
     * `recordsets` override replaces. A copy; other fields come back as they are.
     */
    public function withOffers(string $alias, Field $field): Field
    {
        if ($field->type !== FieldType::Related || ! is_array($own = $field->getParam('recordsets'))
            || ($offered = LinkedTo::offered($alias, $field->name)) === []) {
            return $field;
        }

        return (clone $field)->param('recordsets', array_values(array_unique([...$own, ...$offered])));
    }

    /** Apply to copies, preserving module declarations and cached custom fields. */
    public function resolve(string $model, array $fields): array
    {
        $alias = CustomFieldRegistry::aliasFor($model);
        $overrides = $alias ? ($this->all()[$alias] ?? []) : [];
        $resolved = [];
        foreach ($fields as $position => $field) {
            // Before the administrator's choice, which replaces it.
            $field = $alias ? $this->withOffers($alias, $field) : $field;
            $properties = $overrides[$field->name] ?? [];
            // New module restrictions take precedence over previously saved settings.
            if ($properties !== []) {
                $properties = array_intersect_key($properties, array_flip($this->editable($model, $field)));
            }
            $effective = $field->withAdministratorProperties($properties);
            if ($effective->isRequired() && ! $effective->isInForm() && $field->isInForm()) {
                $effective->inForm();
            }
            $resolved[] = ['field' => $effective, 'position' => $properties['position'] ?? ($position * 10), 'original' => $position];
        }
        usort($resolved, fn (array $a, array $b): int => [$a['position'], $a['original']] <=> [$b['position'], $b['original']]);

        return array_column($resolved, 'field');
    }
}
