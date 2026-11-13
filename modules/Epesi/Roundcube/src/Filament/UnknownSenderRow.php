<?php

namespace Epesi\Modules\Roundcube\Filament;

use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\Mail\Services\UnknownSenders;
use Epesi\Modules\RecordBrowser\Recordset\RecordPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * One row of the Mailbox's "Sender not in Contacts" dialog: an unknown
 * address and what to do with it — add it to an existing contact, create a
 * contact (and a company) for it, add it to a company, or nothing
 * (AI-shared/features/mail/archiving.md, Unknown sender).
 */
class UnknownSenderRow
{
    public const EXISTING = 'existing';

    public const NEW = 'new';

    public const COMPANY = 'company';

    public const SKIP = 'skip';

    /** The company select's "New company" choice. */
    public const NEW_COMPANY = 'new';

    /**
     * The row's first state: names from the header, the domain's company, and
     * the likeliest choice — a suggested contact, else a new one.
     *
     * @param  array{email?: string, name?: string}  $entry
     * @return array<string, mixed>
     */
    public static function defaults(array $entry): array
    {
        $email = mb_strtolower(trim((string) ($entry['email'] ?? '')));
        $names = UnknownSenders::splitName((string) ($entry['name'] ?? ''), $email);
        $senders = app(UnknownSenders::class);
        $suggested = $senders->suggestions($email, $names['first_name'], $names['last_name'])->first();
        $user = Auth::user();

        $choice = match (true) {
            $suggested !== null && $user?->can('update', $suggested) => self::EXISTING,
            (bool) $user?->can('create', Contact::class) => self::NEW,
            default => self::SKIP,
        };

        return [
            'email' => $email,
            'name' => (string) ($entry['name'] ?? ''),
            'choice' => $choice,
            'contact_id' => $choice === self::EXISTING ? $suggested?->getKey() : null,
            'first_name' => $names['first_name'],
            'last_name' => $names['last_name'],
            'company_id' => $senders->companyFor($email)?->getKey(),
            'new_company_name' => UnknownSenders::companyName($email),
            'company_target' => $senders->companyFor($email)?->getKey(),
        ];
    }

    /** "Maria Dahl <maria@siluett.se>", or the address alone. */
    public static function label(array $state): string
    {
        $name = trim((string) ($state['name'] ?? ''));
        $email = (string) ($state['email'] ?? '');

        return $name === '' || mb_strtolower($name) === mb_strtolower($email) ? $email : "{$name} <{$email}>";
    }

    /**
     * @return array<int, mixed>
     */
    public static function schema(): array
    {
        return [
            Hidden::make('email'),
            Hidden::make('name'),
            ToggleButtons::make('choice')
                ->hiddenLabel()
                ->inline()
                ->live()
                ->required()
                ->options([
                    self::EXISTING => __('Existing contact'),
                    self::NEW => __('New contact'),
                    self::COMPANY => __('Company address'),
                    self::SKIP => __("Don't add"),
                ])
                ->disableOptionWhen(fn (string $value): bool => match ($value) {
                    self::NEW => ! Auth::user()?->can('create', Contact::class),
                    default => false,
                }),
            Select::make('contact_id')
                ->label(__('Contact'))
                ->searchable()
                ->native(false)
                ->visible(fn (Get $get): bool => $get('choice') === self::EXISTING)
                ->required(fn (Get $get): bool => $get('choice') === self::EXISTING)
                ->options(fn (Get $get): array => static::suggestionOptions((string) $get('email'), (string) $get('name')))
                ->getSearchResultsUsing(fn (string $search): array => collect(RecordPicker::search('contact', $search))
                    ->mapWithKeys(fn ($title, $id): array => [$id => ($c = Contact::query()->with('company')->find($id)) ? static::contactLabel($c) : $title])->all())
                ->getOptionLabelUsing(fn ($value): ?string => ($c = Contact::query()->with('company')->find($value)) ? static::contactLabel($c) : null)
                ->helperText(__('The address is added to this contact.')),
            Grid::make(3)
                ->visible(fn (Get $get): bool => $get('choice') === self::NEW)
                ->schema([
                    TextInput::make('first_name')->label(__('First Name'))->maxLength(128),
                    TextInput::make('last_name')->label(__('Last Name'))->maxLength(128)
                        ->required(fn (Get $get): bool => $get('choice') === self::NEW),
                    Select::make('company_id')
                        ->label(__('Company'))
                        ->searchable()
                        ->native(false)
                        ->live()
                        ->options(fn (Get $get): array => static::companyOptions($get('company_id')))
                        ->getSearchResultsUsing(fn (string $search): array => static::companySearch($search))
                        ->getOptionLabelUsing(fn ($value): ?string => $value === self::NEW_COMPANY ? __('New company') : RecordPicker::title('company', $value)),
                    TextInput::make('new_company_name')
                        ->label(__('New company name'))
                        ->maxLength(128)
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => $get('company_id') === self::NEW_COMPANY)
                        ->required(fn (Get $get): bool => $get('choice') === self::NEW && $get('company_id') === self::NEW_COMPANY),
                ]),
            Select::make('company_target')
                ->label(__('Company'))
                ->searchable()
                ->native(false)
                ->visible(fn (Get $get): bool => $get('choice') === self::COMPANY)
                ->required(fn (Get $get): bool => $get('choice') === self::COMPANY)
                ->options(fn (Get $get): array => static::companyOptions($get('company_target'), withNew: false))
                ->getSearchResultsUsing(fn (string $search): array => RecordPicker::search('company', $search))
                ->getOptionLabelUsing(fn ($value): ?string => RecordPicker::title('company', $value))
                ->helperText(__('For a shared address such as info@ or orders@.')),
        ];
    }

    /**
     * Do what the row says. Every record goes through the model's own query
     * (visibility) and the policies; a refusal stops the whole dialog.
     *
     * @param  array<string, mixed>  $row
     */
    public static function apply(array $row): void
    {
        $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
        $user = Auth::user();

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        switch ($row['choice'] ?? self::SKIP) {
            case self::EXISTING:
                $contact = RecordPicker::find('contact', $row['contact_id'] ?? null);

                if (! $contact instanceof Contact || ! $user?->can('update', $contact)) {
                    static::refuse(__('You may not add an address to this contact.'));
                }

                static::addAddress($contact, $email);
                break;

            case self::NEW:
                if (! $user?->can('create', Contact::class) || blank($row['last_name'] ?? null)) {
                    static::refuse(__('You may not create contacts.'));
                }

                $companyId = $row['company_id'] ?? null;

                if ($companyId === self::NEW_COMPANY) {
                    if (! static::canCreateCompany() || blank($name = trim((string) ($row['new_company_name'] ?? '')))) {
                        static::refuse(__('You may not create companies.'));
                    }

                    $companyId = Company::create(['company_name' => $name])->getKey();
                } elseif (filled($companyId)) {
                    $companyId = RecordPicker::find('company', $companyId)?->getKey();
                }

                $contact = Contact::create([
                    'first_name' => trim((string) ($row['first_name'] ?? '')),
                    'last_name' => trim((string) $row['last_name']),
                    'company_id' => $companyId ?: null,
                ]);

                static::addAddress($contact, $email);
                break;

            case self::COMPANY:
                $company = RecordPicker::find('company', $row['company_target'] ?? null);

                if (! $company instanceof Company || ! $user?->can('update', $company)) {
                    static::refuse(__('You may not add an address to this company.'));
                }

                static::addAddress($company, $email);
                break;
        }
    }

    /** The record's addresses as they are, plus this one as a Work address. */
    protected static function addAddress(Model $record, string $email): void
    {
        $items = $record->collection('emails')->get()
            ->map(fn ($item): array => ['id' => $item->getKey(), 'kind' => $item->kind, 'value' => $item->value])
            ->all();

        $record->syncCollection('emails', [...$items, ['kind' => 'work', 'value' => $email]]);
    }

    /** Stop the dialog, with nothing saved (the caller's transaction rolls back). */
    protected static function refuse(string $message): never
    {
        Notification::make()->title($message)->danger()->send();

        throw new Halt;
    }

    protected static function canCreateCompany(): bool
    {
        return (bool) Auth::user()?->can('create', Company::class);
    }

    /**
     * @return array<int|string, string>
     */
    protected static function suggestionOptions(string $email, string $name): array
    {
        $names = UnknownSenders::splitName($name, $email);

        return app(UnknownSenders::class)->suggestions($email, $names['first_name'], $names['last_name'])
            ->mapWithKeys(fn (Contact $contact): array => [$contact->getKey() => static::contactLabel($contact)])
            ->all();
    }

    /**
     * Companies matching the search, plus "New company" when the user may create one.
     * Joined with `+`, not `...`: unpacking an array renumbers its integer keys, so
     * every company would be offered under 0, 1, 2… instead of its id.
     *
     * @return array<int|string, string>
     */
    public static function companySearch(string $search): array
    {
        $found = RecordPicker::search('company', $search);

        return static::canCreateCompany() ? [self::NEW_COMPANY => __('New company')] + $found : $found;
    }

    /**
     * The current company (the domain's, pre-selected) and "New company".
     *
     * @return array<int|string, string>
     */
    protected static function companyOptions(mixed $current, bool $withNew = true): array
    {
        $options = $withNew && static::canCreateCompany() ? [self::NEW_COMPANY => __('New company')] : [];

        if (filled($current) && $current !== self::NEW_COMPANY && ($title = RecordPicker::title('company', $current)) !== null) {
            $options[$current] = $title;
        }

        return $options;
    }

    /** Last name, First name — Company: enough to tell two Joe Does apart. */
    public static function contactLabel(Contact $contact): string
    {
        $name = collect([$contact->last_name, $contact->first_name])->filter()->implode(', ');
        $company = $contact->company?->company_name;

        return $company ? "{$name} — {$company}" : $name;
    }
}
