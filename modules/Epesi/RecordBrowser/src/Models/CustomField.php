<?php

namespace Epesi\Modules\RecordBrowser\Models;

use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldSchema;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One administrator-added field — one row of the `custom_fields` table.
 *
 * The DDL that backs a definition is driven from this model's own events rather
 * than from the Filament page that happens to have created it, so a field added
 * by seeder, tinker or import gets its column too. Epesi's
 * `Utils_RecordBrowserCommon::new_record_field()` likewise inserts the
 * definition and runs the `ALTER TABLE` in one call.
 */
class CustomField extends Model
{
    use LogsActivity;

    protected $fillable = [
        'model_type',
        'name',
        'label',
        'type',
        'params',
        'required',
        'position',
        'section',
        'show_in_table',
        'show_in_view',
        'show_in_form',
        'filterable',
        'exportable',
        'help',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'type' => FieldType::class,
            'params' => 'array',
            'required' => 'boolean',
            'position' => 'integer',
            'show_in_table' => 'boolean',
            'show_in_view' => 'boolean',
            'show_in_form' => 'boolean',
            'filterable' => 'boolean',
            'exportable' => 'boolean',
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // The column is named after the row's own id, so it can only be
        // assigned once the row exists. saveQuietly() to avoid re-entering
        // `updated` below with a change this same hook just made.
        static::created(function (self $field): void {
            $field->column = 'cf_'.$field->getKey();
            $field->saveQuietly();

            try {
                app(CustomFieldSchema::class)->add($field);
            } catch (\Throwable $exception) {
                // A definition with no column behind it is worse than no
                // definition: every screen would render a field that silently
                // fails to save. Leave nothing behind instead.
                $field->deleteQuietly();

                throw $exception;
            }
        });

        static::updated(function (self $field): void {
            app(CustomFieldSchema::class)->change($field);
        });

        // Deleting a definition leaves its column (and the data in it) alone —
        // dropping is the separate, confirmed action on the admin screen.
        static::saved(fn () => CustomFieldRegistry::refresh());
        static::deleted(fn () => CustomFieldRegistry::refresh());
    }

    /**
     * The model this field belongs to, resolved through the morph map.
     *
     * @return class-string<Model>|null
     */
    public function modelClass(): ?string
    {
        $class = Relation::getMorphedModel((string) $this->model_type);

        return is_string($class) && class_exists($class) ? $class : null;
    }

    public function modelTable(): ?string
    {
        $class = $this->modelClass();

        return $class ? (new $class)->getTable() : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('custom_field');
    }
}
