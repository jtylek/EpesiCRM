<?php

namespace Epesi\Modules\RecordBrowser\Models;

use Epesi\Modules\CommonData\Facades\CommonData;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * A phone number, as a collection item: a contact's work, mobile and home
 * numbers and fax, a company's switchboard — as many as a record needs, each
 * with its kind (`Phone_Kinds`: Work, Mobile, Home, Fax, Other) and the
 * messenger apps that reach it (`Phone_Messengers`: WhatsApp, Signal, Viber,
 * Telegram). Any recordset takes them with
 * `Field::collection('phones', PhoneNumber::class)`.
 *
 * The kind says which line a number is, the messengers which apps reach it:
 * a mobile on WhatsApp and Signal is one item.
 *
 * The number is kept as typed, and its digits alone beside it (`digits`,
 * indexed, set on every save), so "600100200" finds "600 100 200" and an
 * incoming number can be matched to its owner.
 *
 * @property ?string $value
 * @property ?string $digits
 * @property ?list<string> $messengers
 */
class PhoneNumber extends CollectionItem
{
    protected $table = 'epesi_recordbrowser_phone_numbers';

    /**
     * Where each messenger the list starts with opens a chat, given the
     * number's digits with its country code. A messenger an administrator
     * adds has none: CommonData holds only a key and a label.
     */
    protected const MESSENGER_LINKS = [
        'whatsapp' => 'https://wa.me/%s',
        'signal' => 'https://signal.me/#p/+%s',
        'viber' => 'viber://chat?number=%%2B%s',
        'telegram' => 'https://t.me/+%s',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $number): void {
            $number->digits = static::digitsOf($number->value);
        });
    }

    public static function fields(): array
    {
        // The apps' links need the country code, which a number starting
        // with + or 00 carries: a messenger ticked on one without warns.
        $lacksCountryCode = fn (Get $get, mixed $state): bool => filled($state) && filled($get('value')) && ! static::isInternational($get('value'));

        return [
            // The list's column: a record's first number.
            Field::phone('value')->label('Phone number')->required()->inTable()
                ->formUsing(fn (TextInput $input): TextInput => $input->live(onBlur: true)),
            Field::commonData('messengers', static::messengers(), multiple: true)
                ->param('order', 'position')
                ->filterable()
                ->formUsing(fn (Select $select): Select => $select
                    ->live()
                    ->hint(fn (Get $get, mixed $state): ?string => $lacksCountryCode($get, $state)
                        ? __('The links need + and the country code')
                        : null)
                    ->hintIcon(fn (Get $get, mixed $state): ?Heroicon => $lacksCountryCode($get, $state) ? Heroicon::OutlinedExclamationTriangle : null)
                    ->hintColor('warning')),
        ];
    }

    public static function kinds(): string
    {
        return 'Phone_Kinds';
    }

    /** The CommonData list of the apps a number can be reached on. */
    public static function messengers(): string
    {
        return 'Phone_Messengers';
    }

    public static function addActionLabel(): string
    {
        return __('Add phone number');
    }

    /** Also by the digits alone: "600100200" finds "600 100 200". */
    public static function searchColumns(): array
    {
        return [...parent::searchColumns(), 'digits'];
    }

    /** The digits of what was typed, for the digits column; null when there are none. */
    public static function searchTerm(string $column, string $search): ?string
    {
        return $column === 'digits' ? static::digitsOf($search) : $search;
    }

    /** "+48 600-100-200" → "48600100200"; null for no digits at all. */
    public static function digitsOf(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }

    /** Whether $value carries its country code: it starts with + or 00. */
    public static function isInternational(?string $value): bool
    {
        return (bool) preg_match('/^\s*(\+|00)/', (string) $value);
    }

    /** The number as typed. */
    public function summary(): string
    {
        return (string) $this->value;
    }

    /**
     * One badge per messenger, each opening the app on this number — when
     * the number has its country code and the app is one the links are
     * known for. Otherwise the badge alone.
     */
    public function links(): array
    {
        $digits = $this->internationalDigits();
        $labels = CommonData::array(static::messengers(), 'position');

        return array_values(array_map(fn (string $key): array => [
            'label' => $labels[$key] ?? $key,
            'url' => $digits !== null && isset(static::MESSENGER_LINKS[$key]) ? sprintf(static::MESSENGER_LINKS[$key], $digits) : null,
        ], array_filter((array) $this->messengers, is_string(...))));
    }

    /** The messengers' names before an administrator's fields: "Mobile: +48 600 100 200 (WhatsApp, Signal)". */
    protected function historyDetails(): array
    {
        return [...array_column($this->links(), 'label'), ...parent::historyDetails()];
    }

    /** The digits with the country code first, without its + or 00; null for a number without one. */
    protected function internationalDigits(): ?string
    {
        return static::isInternational($this->value)
            ? static::digitsOf(preg_replace('/^\s*00/', '', (string) $this->value))
            : null;
    }
}
