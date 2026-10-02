<?php

namespace Epesi\Modules\RecordBrowser\Recordset;

/**
 * The kinds of field a recordset can declare — the port of Epesi's
 * `<table>_field.type` (Utils_RecordBrowserCommon::actual_db_type()).
 *
 * The value is what `custom_fields.type` stores for an administrator-added
 * field, so renaming a case is a stored-data change and needs a migration.
 *
 * Some of Epesi's types aren't cases here: `hidden` is `->onlyInTable()`/
 * `active = false`, and `page_split` is `Field::section()`. `currency` and
 * `calculated` have no equivalent yet — Decimal drops a currency value's
 * currency, and an accessor-backed field has no column to sort or search on.
 * A select over several named recordsets (`contact,company`) becomes one
 * Relation(s) field per target; one over any recordset (`__RECORDSETS__`) is
 * Related. Collection has no legacy counterpart: what a record has none or
 * many of (its addresses, phone numbers, online accounts), kept in a table of
 * its own per collection type.
 * See AI-shared/Epesi-custom-fields.md.
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
    case Customer = 'customer';
    case Customers = 'customers_field';
    case Email = 'email';
    case Url = 'url';
    case Phone = 'phone';
    case Autonumber = 'autonumber';
    case File = 'file';
    case Related = 'related';
    case Collection = 'collection';

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
            self::Customer => __('Contact or company'),
            self::Customers => __('Several contacts or companies'),
            self::Email => __('E-mail'),
            self::Url => __('Web address'),
            self::Phone => __('Phone number'),
            self::Autonumber => __('Automatic number'),
            self::File => __('File'),
            self::Related => __('Link to any record'),
            self::Collection => __('Collection'),
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
        return in_array($this, [self::Multiselect, self::Relations, self::Related, self::Customers, self::Collection], true);
    }

    /**
     * Whether the value is a column on the record's own table — not for a
     * value derived from the key (Autonumber), nor for links kept elsewhere: a
     * pivot, or for an administrator's field the shared link table
     * (Relations), or that table (Related, Customers), nor for a collection's
     * items, rows of the collection type's own table. Customer is two columns
     * (`{name}_type`/`{name}_id`, a morphTo pair) rather than one, so it's
     * checked its own way too (RecordsetCheckCommand).
     */
    public function hasColumn(): bool
    {
        return ! in_array($this, [self::Autonumber, self::Relations, self::Related, self::Customers, self::Collection, self::Customer], true);
    }

    /**
     * Types a collection item (an address) can take, for the fields an
     * administrator adds to one: a value in a column of the item's own table.
     * Not a link, a file or a number derived from the key, which need a
     * record of their own to hang on, and not a collection inside a
     * collection.
     */
    public function fitsCollectionItem(): bool
    {
        return ! in_array($this, [self::Relation, self::Relations, self::Related, self::Customers, self::File, self::Autonumber, self::Collection, self::Customer], true);
    }

    /**
     * Types an administrator may add from the GUI — every one, since the
     * form picks a link field's target recordset, a shared list's array and a
     * collection's type. Kept as the one place to withhold a type that a form
     * can't offer yet. Customer(s) isn't offered: its target models are a
     * module's code-time decision (Field::customer()'s/Field::customers()'s
     * $models), not something a GUI form picks.
     */
    public function isAdministratorDefinable(): bool
    {
        return ! in_array($this, [self::Customer, self::Customers], true);
    }
}
