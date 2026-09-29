<?php

namespace Epesi\Modules\RecordBrowser\Models;

use Closure;
use Epesi\Modules\RecordBrowser\Extensions\RecordExtensions;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;

/**
 * An e-mail address, as a collection item: a contact's or a company's own
 * address and any extra one, each with its kind (`Email_Kinds`: Work,
 * Private, Other) — as many as a record needs. Any recordset takes them with
 * `Field::collection('emails', EmailAddress::class)`.
 *
 * An address belongs to one record: it is unique across every record using
 * this type (contacts, companies, and any module's recordset that takes it),
 * so mail from it files to exactly one of them. The form's rule
 * (uniqueRule()) checks this before save and names the record that already
 * has it; a plain unique index on `value` enforces it regardless.
 *
 * @property ?string $value
 */
class EmailAddress extends CollectionItem
{
    protected $table = 'epesi_recordbrowser_email_addresses';

    protected static function booted(): void
    {
        static::saving(function (self $address): void {
            $address->value = mb_strtolower(trim((string) $address->value));
        });
    }

    public static function fields(): array
    {
        return [
            // The list's column: a record's first address.
            Field::email('value')->label('Address')->required()->inTable()
                ->formUsing(fn (TextInput $input): TextInput => $input
                    ->rule(fn (Get $get): Closure => static::uniqueRule($get))),
        ];
    }

    public static function kinds(): string
    {
        return 'Email_Kinds';
    }

    public static function addActionLabel(): string
    {
        return __('Add e-mail address');
    }

    /** The address as typed. */
    public function summary(): string
    {
        return (string) $this->value;
    }

    /** Opens Mail's compose page for the owner, as the old `email` column did. */
    public function url(): ?string
    {
        if (blank($this->value) || $this->owner === null) {
            return null;
        }

        return RecordExtensions::emailUrlFor($this->owner, $this->value);
    }

    /**
     * Not whether the address is real, but whether another record already
     * has it: an address belongs to one record, so mail from it files to
     * one. Excludes this item itself (by id, from the repeater's own hidden
     * field), so re-saving an unchanged address never conflicts with itself.
     */
    protected static function uniqueRule(Get $get): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            $value = mb_strtolower(trim((string) $value));

            if ($value === '') {
                return;
            }

            $conflict = static::query()
                ->when($get('id'), fn ($query, $id) => $query->whereKeyNot($id))
                ->whereRaw('LOWER(value) = ?', [$value])
                ->with('owner')
                ->first();

            if ($conflict === null) {
                return;
            }

            $owner = $conflict->owner;

            $fail($owner instanceof Model
                ? __('This address is already used by :record.', ['record' => LinkedRecords::title($owner)])
                : __('This address is already used by another record.'));
        };
    }
}
