<?php

namespace Epesi\Modules\RecordBrowser\Recordset;

use Epesi\Modules\RecordBrowser\Models\CustomField;
use Illuminate\Database\Eloquent\Builder;

class RecordsetCsvExport
{
    /** @param class-string<RecordsetResource> $resource */
    public function __construct(private readonly string $resource) {}

    /** @return list<Field> */
    public function fields(): array
    {
        $alias = app($this->resource::getModel())->getMorphClass();
        $custom = CustomField::query()->where('model_type', $alias)->where('exportable', true)->pluck('column')->all();

        return array_values(array_filter(
            $this->resource::resolvedFields(),
            fn (Field $field): bool => $field->isInView()
                && (! str_starts_with($field->name, 'cf_') || in_array($field->name, $custom, true)),
        ));
    }

    /** @param resource $out */
    public function write($out, Builder $query): void
    {
        $fields = $this->fields();
        $this->row($out, [__('Record ID'), ...array_map(fn (Field $field): string => $this->safeCell(__($field->getLabel())), $fields)]);

        // lazy() bounds PHP memory and preserves the table's current filters,
        // search, browse mode and sort. The key breaks ties in the chosen sort.
        $query->orderBy($query->getModel()->getQualifiedKeyName());
        foreach ($query->lazy(200) as $record) {
            $this->row($out, [
                (string) $record->getKey(),
                ...array_map(fn (Field $field): string => $this->safeCell(
                    $field->exportValue($record),
                    numeric: in_array($field->type, [FieldType::Integer, FieldType::Decimal, FieldType::Currency], true),
                ), $fields),
            ]);
        }
    }

    /** @param resource $out @param list<string> $values */
    private function row($out, array $values): void
    {
        fputcsv($out, $values, ',', '"', '', "\n");
    }

    private function safeCell(string $value, bool $numeric = false): string
    {
        // Quoting alone does not prevent spreadsheets from treating a text
        // cell as a formula. A negative number remains numeric.
        return ! $numeric && preg_match('/^[\x00-\x20]*[=+\-@]/', $value) ? "'".$value : $value;
    }
}
