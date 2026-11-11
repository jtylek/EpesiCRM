<?php

namespace App\Services\LegacyImport;

/**
 * Which Epesi 3 custom fields the extra columns of a legacy RecordBrowser data table become. Pure (arrays
 * in, arrays out), so the Migration Wizard's assessment page and the importers decide from the same code.
 *
 * Rule: every column of `<tab>_data_1` that stock Epesi 1.9.1 does not have (LegacyStockColumns) is a field
 * added by another module or by custom changes, and is carried over as a custom field of the Epesi 3 model;
 * nothing is dropped silently. The mapping is AI-shared/recordbrowser/fields.md ("Legacy field types
 * against the port"): every legacy type has a port type. The type comes from the column's `<tab>_field` row
 * (legacy `type`, `param` and the display callbacks, because legacy stores an e-mail as `text` and only the
 * callback tells) and, for a column no row declares, from the column's SQL type.
 *
 * One column can become several fields (a `select` over `contact,company` becomes one link per recordset).
 * Each entry says how its value is filled (`fill`): `value` (a column of the record, filled with the row),
 * `relation` / `links` (links to other records, filled by Importer::linkCustomRelations() once those records
 * exist), or `none` (an autonumber: derived from the record key, nothing to copy). `pending` names the
 * reason a field is added but its data is not copied yet (files).
 *
 * Legacy `<tab>_field.field` is the field's display name ("Account Manager"); its column is
 * `f_<fieldId(name)>`.
 */
final class LegacyCustomFieldPlanner
{
    /** Legacy recordset tab => morph alias of the model its records become. */
    public const RECORD_TARGETS = ['company' => 'company', 'contact' => 'contact', 'task' => 'task', 'phonecall' => 'phone_call', 'crm_meeting' => 'meeting'];

    private const TARGET_LABELS = ['company' => 'Company', 'contact' => 'Contact', 'task' => 'Task', 'phonecall' => 'Phone call', 'crm_meeting' => 'Meeting'];

    public const DECIMALS = 4;

    /** Epesi 3's cap per model (CustomFieldSchema::MAX_FIELDS_PER_MODEL); kept here so the pure code needs no framework. */
    public const MAX_FIELDS = 64;

    /** Legacy `get_field_id()`: lower case, anything but a-z, 0-9 and | becomes an underscore. */
    public static function fieldId(string $name): string
    {
        return preg_replace('/[^a-z0-9|]/', '_', strtolower($name)) ?? '';
    }

    /**
     * @param  list<string>  $columns  the actual columns of `<tab>_data_1`
     * @param  array<string, array{type_name?: string, type?: string}>  $columnTypes  column => SQL type (DATA_TYPE / COLUMN_TYPE)
     * @param  list<array<string, mixed>>  $fieldRows  rows of `<tab>_field`
     * @param  array<string, list<string>>  $callbacks  field display name => callbacks (`<tab>_callback`)
     * @return list<array<string, mixed>> the fields the extra columns become, in table order
     */
    public function plan(string $tab, array $columns, array $columnTypes, array $fieldRows, array $callbacks): array
    {
        $stock = LegacyStockColumns::for($tab.'_data_1');
        if ($stock === null) {
            return [];
        }
        $declared = [];
        foreach ($fieldRows as $row) {
            if (is_string($row['field'] ?? null)) {
                $declared['f_'.self::fieldId($row['field'])] ??= $row;
            }
        }
        $entries = [];
        $taken = [];
        $known = [...$stock, ...(LegacyStockColumns::IMPORTER_COLUMNS[$tab.'_data_1'] ?? [])];
        foreach (array_values(array_diff($columns, $known)) as $column) {
            $row = $declared[$column] ?? null;
            $found = $row !== null
                ? $this->fromField($column, $row, $callbacks[(string) $row['field']] ?? [], $columnTypes[$column] ?? [])
                : [$this->fromColumn($column, $columnTypes[$column] ?? [], null)];
            foreach ($found as $entry) {
                // A name is unique per model.
                $base = $entry['name'];
                for ($i = 2; isset($taken[$entry['name']]); $i++) {
                    $entry['name'] = mb_substr($base, 0, 60).'_'.$i;
                }
                $taken[$entry['name']] = true;
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param  list<string>  $callbacks
     * @return list<array<string, mixed>>
     */
    private function fromField(string $column, array $row, array $callbacks, array $sql): array
    {
        $legacyName = (string) $row['field'];
        $type = strtolower(trim((string) ($row['type'] ?? '')));
        $param = (string) ($row['param'] ?? '');
        $label = trim((string) ($row['caption'] ?? '')) !== '' ? trim((string) $row['caption']) : $legacyName;
        $entry = $this->base($column, $this->slug($legacyName), $label, $type, true);
        $entry['required'] = (bool) ($row['required'] ?? false);
        $entry['position'] = (int) ($row['position'] ?? 0);
        $entry['show_in_table'] = (bool) ($row['visible'] ?? false);
        $entry['filterable'] = (bool) ($row['filter'] ?? false);
        $entry['exportable'] = (bool) ($row['export'] ?? true);
        $entry['help'] = trim((string) ($row['help'] ?? '')) !== '' ? (string) $row['help'] : null;
        $entry['active'] = (bool) ($row['active'] ?? true);
        $callbackNames = implode(' ', $callbacks);

        switch ($type) {
            case 'text':
                $length = ctype_digit($param) && (int) $param > 0 ? (int) $param : 255;
                foreach (['::display_email' => 'email', '::display_phone' => 'phone', '::display_webaddress' => 'url'] as $needle => $kind) {
                    if (str_contains($callbackNames, $needle)) {
                        return [$this->as($entry, $kind, ['length' => min($length, 255)])];
                    }
                }

                return [$length > 255 ? $this->as($entry, 'long_text') : $this->as($entry, 'text', ['length' => $length])];
            case 'long text':
                return [$this->as($entry, 'long_text')];
            case 'integer':
                return [$this->as($entry, 'integer')];
            case 'float':
                return [$this->as($entry, 'decimal', ['decimals' => self::DECIMALS])];
            case 'checkbox':
                return [$this->as($entry, 'boolean')];
            case 'date':
                return [$this->as($entry, 'date')];
            case 'timestamp':
                return [$this->as($entry, 'datetime', ['minutes_step' => 5])];
            case 'time':
                return [$this->as($entry, 'time', ['minutes_step' => 5])];
            case 'currency':
                return [$this->as($entry, 'currency', ['decimals' => 2])];
            case 'file':
                $entry['pending'] = 'the files are moved by the file ledger, which does not carry this field yet';

                return [$this->as($entry, 'file', ['multiple' => true, 'max_size_mb' => 50])];
            case 'autonumber':
                // Derived from the record key in Epesi 3 (no column), so the legacy numbers are not copied.
                $entry['fill'] = 'none';

                return [$this->as($entry, 'autonumber', ['prefix' => '', 'pad_length' => 0, 'pad_mask' => '0'])];
            case 'commondata':
                $path = explode('::', (string) preg_replace('/^\d+__/', '', $param))[0];

                return [$this->as($entry, 'commondata', ['array' => $path, 'multiple' => false])];
            case 'select':
            case 'multiselect':
                return $this->selectFields($entry, $type === 'multiselect', $param);
            case 'hidden':
            case 'calculated':
            case 'page_split':
            default:
                // A value stored in the column whatever the field means: typed from the column (hidden fields are
                // kept out of the forms and views; a calculated field keeps its stored copy as a plain value).
                $field = $this->fromColumn($column, $sql, $entry);
                if ($type === 'hidden') {
                    $field['show_in_form'] = false;
                    $field['show_in_view'] = false;
                    $field['show_in_table'] = false;
                }

                return [$field];
        }
    }

    /** @return list<array<string, mixed>> */
    private function selectFields(array $entry, bool $multiple, string $param): array
    {
        $head = explode(';', $param)[0];
        $parts = explode('::', $head);
        $target = $parts[0];

        if ($target === '__COMMON__') {
            return [$this->as($entry, 'commondata', ['array' => (string) ($parts[1] ?? ''), 'multiple' => $multiple])];
        }
        if ($target === '__RECORDSETS__') {
            $entry['fill'] = 'links';
            $entry['typed'] = true;

            return [$this->as($entry, 'related', ['recordsets' => []])];
        }
        $targets = array_values(array_filter(array_map('trim', explode(',', $target))));
        $known = array_values(array_filter($targets, fn (string $tab): bool => isset(self::RECORD_TARGETS[$tab])));
        if ($targets === [] || count($known) !== count($targets)) {
            $unknown = array_values(array_diff($targets, $known));
            $entry['pending'] = 'it links to the legacy recordset '.($unknown === [] ? '(none named)' : "'".implode("', '", $unknown)."'").', which Epesi 3 does not have';
        }
        $fields = [];
        foreach ($known as $tab) {
            $one = $entry;
            $one['relation_tab'] = $tab;
            $one['typed'] = count($targets) > 1;
            if (count($targets) > 1) {
                $one['name'] = mb_substr($one['name'], 0, 52).'_'.$tab;
                $one['label'] = $one['label'].' ('.(self::TARGET_LABELS[$tab] ?? $tab).')';
            }
            $one['fill'] = $multiple ? 'links' : 'relation';
            $fields[] = $this->as($one, $multiple ? 'relations' : 'relation', ['recordset' => self::RECORD_TARGETS[$tab]]);
        }
        if ($fields === []) {
            // Nothing to link to: the field is still added (as an unlinked text) so its column is not forgotten,
            // and the data stays in the legacy table until a target exists.
            return [$this->as($entry, 'text', ['length' => 255])];
        }

        return $fields;
    }

    /** A column no `<tab>_field` row declares, or one whose value is just a stored copy: typed from the column itself. */
    private function fromColumn(string $column, array $sql, ?array $entry): array
    {
        $bare = substr($column, 0, 2) === 'f_' ? substr($column, 2) : $column;
        $sqlType = strtolower((string) ($sql['type_name'] ?? ''));
        $entry ??= $this->base($column, $this->slug($bare), ucwords(str_replace('_', ' ', $bare)), $sqlType === '' ? 'unknown' : $sqlType, false);
        preg_match('/\((\d+)\)/', strtolower((string) ($sql['type'] ?? $sqlType)), $match);
        $length = isset($match[1]) ? (int) $match[1] : 255;

        return match (true) {
            $sqlType === 'tinyint' && $length === 1 => $this->as($entry, 'boolean'),
            in_array($sqlType, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'], true) => $this->as($entry, 'integer'),
            in_array($sqlType, ['decimal', 'numeric', 'float', 'double'], true) => $this->as($entry, 'decimal', ['decimals' => self::DECIMALS]),
            $sqlType === 'date' => $this->as($entry, 'date'),
            in_array($sqlType, ['datetime', 'timestamp'], true) => $this->as($entry, 'datetime', ['minutes_step' => 5]),
            $sqlType === 'time' => $this->as($entry, 'time', ['minutes_step' => 5]),
            in_array($sqlType, ['tinytext', 'text', 'mediumtext', 'longtext'], true) => $this->as($entry, 'long_text'),
            in_array($sqlType, ['varchar', 'char'], true) && $length <= 255 => $this->as($entry, 'text', ['length' => $length]),
            // Whatever else (a blob, an enum, a very long varchar): kept as text, so no column is left out.
            default => $this->as($entry, 'long_text'),
        };
    }

    private function base(string $column, string $name, string $label, string $legacyType, bool $declared): array
    {
        return [
            'column' => $column,
            'legacy_name' => $label,
            'legacy_type' => $legacyType,
            'declared' => $declared,
            'name' => $name,
            'label' => $label,
            'type' => null,
            'type_label' => null,
            'params' => [],
            'fill' => 'value',
            'typed' => false,
            'relation_tab' => null,
            'pending' => null,
            'required' => false,
            'position' => 0,
            'show_in_table' => false,
            'show_in_view' => true,
            'show_in_form' => true,
            'filterable' => false,
            'exportable' => true,
            'help' => null,
            'active' => true,
        ];
    }

    private function as(array $entry, string $type, array $params = []): array
    {
        $entry['type'] = $type;
        $entry['params'] = $params;
        $entry['type_label'] = match ($type) {
            'text' => 'Text',
            'long_text' => 'Long text',
            'integer' => 'Integer',
            'decimal' => 'Decimal',
            'boolean' => 'Checkbox',
            'date' => 'Date',
            'datetime' => 'Date and time',
            'time' => 'Time',
            'email' => 'Email',
            'phone' => 'Phone',
            'url' => 'Web address',
            'currency' => 'Amount with currency',
            'file' => 'File',
            'autonumber' => 'Autonumber',
            'commondata' => ($params['multiple'] ?? false) ? 'Shared list, several choices' : 'Shared list choice',
            'related' => 'Link to any record',
            'relation' => (self::TARGET_LABELS[$entry['relation_tab']] ?? 'Record').' link',
            'relations' => (self::TARGET_LABELS[$entry['relation_tab']] ?? 'Record').' links (several)',
            default => $type,
        };

        return $entry;
    }

    /** A stable code name for the definition (unique per model, at most 60 characters, never starting with a digit). */
    private function slug(string $name): string
    {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($name)) ?? '', '_');
        if ($slug === '' || ctype_digit($slug[0])) {
            $slug = 'f_'.$slug;
        }

        return mb_substr($slug, 0, 60);
    }

    /**
     * One legacy value as the custom field's value, for the entries filled with the row (`fill = value`).
     * A currency yields [amount, code] (two columns), a shared-list choice with several values a list.
     */
    public static function decode(array $entry, mixed $raw): mixed
    {
        $empty = $raw === null || $raw === '';

        return match ($entry['type']) {
            'text', 'email', 'phone', 'url' => $empty || ($v = trim(html_entity_decode((string) $raw, ENT_QUOTES | ENT_HTML5))) === '' ? null : mb_substr($v, 0, (int) ($entry['params']['length'] ?? 255)),
            'long_text' => $empty ? null : (string) $raw,
            'integer' => ! $empty && is_numeric($raw) ? (int) $raw : null,
            'decimal' => ! $empty && is_numeric($raw) ? number_format((float) $raw, (int) ($entry['params']['decimals'] ?? self::DECIMALS), '.', '') : null,
            'boolean' => ! $empty && (int) $raw !== 0,
            'date' => ! $empty && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $raw) === 1 && ! str_starts_with((string) $raw, '0000') ? substr((string) $raw, 0, 10) : null,
            'datetime' => ! $empty && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $raw) === 1 && ! str_starts_with((string) $raw, '0000') ? (string) $raw : null,
            'time' => ! $empty && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) $raw) === 1 ? (string) $raw : null,
            'currency' => LegacyMoney::parse($empty ? null : (string) $raw),
            'commondata' => ($entry['params']['multiple'] ?? false)
                ? ($empty ? [] : LegacyValue::multi((string) $raw))
                : ($empty ? null : trim((string) $raw)),
            default => null,
        };
    }
}
