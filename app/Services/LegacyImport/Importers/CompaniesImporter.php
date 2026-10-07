<?php

namespace App\Services\LegacyImport\Importers;

use App\Enums\RecordPermission;
use App\Services\LegacyImport\Importer;
use App\Services\LegacyImport\LegacyValue;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\RecordBrowser\Models\Address;

/**
 * Legacy `company` recordset (CRM_ContactsInstall::install()'s company
 * fields) -> Epesi\Modules\CRM\Companies\Models\Company. Field list read directly from the live
 * `company_field` table, matching what Phase 1 built Company's schema from.
 */
class CompaniesImporter extends Importer
{
    public function legacyTab(): string
    {
        return 'company';
    }

    public function modelClass(): string
    {
        return Company::class;
    }

    public function logName(): string
    {
        return 'company';
    }

    /**
     * email is no longer a column here (it's an EmailAddress collection
     * item) — its own "already taken" check is withoutTakenEmails() instead.
     */
    protected function uniqueColumns(): array
    {
        return [];
    }

    protected function trackedFields(): array
    {
        return [
            'company_name' => 'company_name',
            'short_name' => 'short_name',
            'phone' => 'phone',
            'fax' => 'fax',
            'email' => 'email',
            'web_address' => 'web_address',
            'memo' => 'memo',
            'group' => 'groups',
            'permission' => 'permission',
            'address_1' => 'address_1',
            'address_2' => 'address_2',
            'city' => 'city',
            'country' => 'country',
            'zone' => 'zone',
            'postal_code' => 'postal_code',
            'tax_id' => 'tax_id',
        ];
    }

    private const ADDRESS_FIELDS = ['address_1', 'address_2', 'city', 'country', 'zone', 'postal_code'];

    /** Legacy field => the kind its number becomes, in order. */
    private const PHONES = ['phone' => 'work', 'fax' => 'fax'];

    /** The address, the phone, the fax, the e-mail and the web address are collection items now, not columns. */
    protected function historyOnlyColumns(): array
    {
        return [...self::ADDRESS_FIELDS, ...array_keys(self::PHONES), 'web_address', 'email'];
    }

    /**
     * The address as a Business item, when it has a street, a city or a
     * postal code (Address::isAddress()); the phone and the fax as phone
     * numbers of kinds Work and Fax; the web address as an online account
     * of kind Website.
     */
    protected function collections(object $row): array
    {
        $address = [];

        foreach (self::ADDRESS_FIELDS as $column) {
            $value = trim((string) ($row->{"f_{$column}"} ?? ''));
            $address[$column] = $value === '' ? null : $value;
        }

        $phones = [];

        foreach (self::PHONES as $field => $kind) {
            if (($number = trim((string) ($row->{"f_{$field}"} ?? ''))) !== '') {
                $phones[] = ['kind' => $kind, 'value' => $number];
            }
        }

        $web = trim((string) ($row->f_web_address ?? ''));

        $email = mb_strtolower(trim((string) ($row->f_email ?? '')));
        $emails = $this->withoutTakenEmails($row, array_filter([
            $email,
            ...$this->legacyExtraEmails($row, 'company'),
        ]));

        return [
            'addresses' => Address::isAddress($address) ? [['kind' => 'business', ...$address]] : [],
            'phones' => $phones,
            'online_accounts' => $web === '' ? [] : [['kind' => 'website', 'value' => $web]],
            'emails' => array_map(
                fn (string $value, int $i): array => ['kind' => $i === 0 && $value === $email ? 'work' : 'other', 'value' => $value],
                array_values($emails),
                array_keys(array_values($emails)),
            ),
        ];
    }

    protected function decodeTrackedValue(string $legacyField, ?string $raw): array
    {
        $column = $this->trackedFields()[$legacyField];

        return match ($legacyField) {
            'group' => [$column => $this->commonDataKeys(LegacyValue::multi($raw), 'Companies_Groups')],
            'permission' => [$column => $raw !== null && $raw !== '' ? (int) $raw : RecordPermission::Public->value],
            'email' => [$column => $raw !== '' ? $raw : null],
            // Plain text, stored through htmlspecialchars() ("A&amp;J").
            default => [$column => $raw !== '' && $raw !== null ? ($column === 'memo' ? $raw : html_entity_decode($raw, ENT_QUOTES | ENT_HTML5)) : null],
        };
    }
}
