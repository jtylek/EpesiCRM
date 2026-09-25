<?php

namespace Epesi\Modules\RecordBrowser\Recordset;

/**
 * The kinds of field a recordset can declare — the port of Epesi's
 * `<table>_field.type` (Utils_RecordBrowserCommon::actual_db_type()).
 *
 * The value is what `custom_fields.type` stores for an administrator-added
 * field, so renaming a case is a stored-data change and needs a migration.
 *
 * Deliberately smaller than Epesi's list: `currency` collapses into Decimal
 * (Epesi's 128-char encoded string is not worth reproducing), `calculated` is
 * an Eloquent accessor and so is code-only, `hidden` is `->onlyInTable()`/
 * `active = false`, and `page_split` is `Field::section()`. `commondata` and
 * `file` wait on a reference-data store and a disk convention respectively.
 */
enum FieldType: string
{
    case Text = 'text';
    case LongText = 'long_text';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Date = 'date';
    case DateTime = 'datetime';
    case Time = 'time';
    case Select = 'select';
    case Multiselect = 'multiselect';
    case CommonData = 'commondata';
    case Relation = 'relation';
    case Relations = 'relations';
    case Email = 'email';
    case Url = 'url';
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::Text => __('Text'),
            self::LongText => __('Long text'),
            self::Integer => __('Integer'),
            self::Decimal => __('Decimal'),
            self::Boolean => __('Checkbox'),
            self::Date => __('Date'),
            self::DateTime => __('Date and time'),
            self::Time => __('Time'),
            self::Select => __('Select'),
            self::Multiselect => __('Multiple select'),
            self::CommonData => __('Shared list'),
            self::Relation => __('Link to one record'),
            self::Relations => __('Link to many records'),
            self::Email => __('E-mail'),
            self::Url => __('Web address'),
            self::Phone => __('Phone number'),
        };
    }

    /**
     * Stored in a plain string column and rendered with a TextInput — the
     * difference is validation and the keyboard a phone shows.
     */
    public function isTextual(): bool
    {
        return in_array($this, [self::Text, self::Email, self::Url, self::Phone], true);
    }

    /**
     * Backed by a real relationship rather than a scalar column, so the engine
     * reads the related resource for labels and links instead of the value.
     */
    public function isRelational(): bool
    {
        return in_array($this, [self::Relation, self::Relations], true);
    }

    /**
     * Holds several values at once — rendered as a badge list rather than a
     * single value, and never a plain sortable column.
     */
    public function isMultiple(): bool
    {
        return in_array($this, [self::Multiselect, self::Relations], true);
    }

    /**
     * Types an administrator may add from the GUI. Relations are excluded in
     * v1: picking a target recordset needs a model chooser and a foreign key
     * that survives the target being uninstalled, which is its own piece of
     * work — a module declaring `Field::relation()` in code is unaffected.
     *
     * CommonData is excluded for the same shape of reason: the field is
     * meaningless without an array to point at, and offering the type before
     * the form can offer that picker would only produce empty selects.
     */
    public function isAdministratorDefinable(): bool
    {
        return ! $this->isRelational() && $this !== self::CommonData;
    }
}
