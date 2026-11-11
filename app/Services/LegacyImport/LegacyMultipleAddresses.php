<?php

namespace App\Services\LegacyImport;

use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Models\Address;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Legacy Premium_MultipleAddresses (`premium_multiple_addresses_data_1`) -> Address collection items.
 *
 * Rule "owner + company" (user decision):
 *  - every active row that holds an address (Address::isAddress()) becomes a Shipping address of the
 *    record it belongs to (`f_record_type`/`f_record_id`: a contact, or a company);
 *  - when the row's company name (trimmed, compared without regard to case, as legacy's database does)
 *    is the name of exactly one active legacy company, the address is also added to that company. A name
 *    that matches no company or several is never guessed: it is reported (warnings());
 *  - the row's nickname and first/last name are not kept (an Address has no name fields).
 *
 * The importers ask for the items of the record they are importing (forRecord()), so a re-run replaces
 * them like every other collection item and never duplicates. The same class counts the rows for the
 * Migration Wizard's verification (verification()), which is what releases the legacy table.
 */
final class LegacyMultipleAddresses
{
    public const TABLE = 'premium_multiple_addresses_data_1';

    public const KIND = 'shipping';

    private const FIELDS = ['address_1', 'address_2', 'city', 'postal_code', 'country', 'zone'];

    /** Legacy record type => the recordset table that holds the owner. */
    private const OWNERS = ['contact' => 'contact_data_1', 'company' => 'company_data_1'];

    private ConnectionInterface $legacy;

    /** @var list<array{id: int, owner: string, owner_id: int, address: array<string, ?string>, company_id: ?int}>|null */
    private ?array $entries = null;

    /** @var list<string> */
    private array $warnings = [];

    private int $total = 0;

    /** @var array<string, list<string>> */
    private array $itemKeys = [];

    public function __construct(?ConnectionInterface $legacy = null)
    {
        $this->legacy = $legacy ?? DB::connection('legacy');
    }

    public function exists(): bool
    {
        return $this->legacy->getSchemaBuilder()->hasTable(self::TABLE);
    }

    /**
     * The Shipping items for one record: its own rows, and for a company the copies of the rows
     * whose company name points at it. Identical addresses appear once.
     *
     * @param  'contact'|'company'  $type
     * @return list<array<string, mixed>>
     */
    public function forRecord(string $type, int $legacyId): array
    {
        $items = [];

        foreach ($this->entries() as $entry) {
            if (($entry['owner'] === $type && $entry['owner_id'] === $legacyId) || ($type === 'company' && $entry['company_id'] === $legacyId)) {
                $items[self::key($entry['address'])] ??= ['kind' => self::KIND, ...$entry['address']];
            }
        }

        return array_values($items);
    }

    /**
     * What could not be placed, one line each: an owner that does not exist, a company name that matches
     * no company or more than one. Rows without a company name are not mentioned.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        $this->entries();

        return $this->warnings;
    }

    /**
     * Counted against the database now, for the Migration Wizard: the legacy rows, the ones that are not
     * imported by design (inactive, no street/city/postal code, an owner that does not exist) and the
     * imported ones, and how many of those lack an item on the record(s) they belong to.
     *
     * @return array{source: int, skipped: int, imported: int, missing: int}
     */
    public function verification(): array
    {
        $entries = $this->entries();
        $contacts = LegacyIdMap::for(Contact::class);
        $companies = LegacyIdMap::for(Company::class);
        $missing = 0;

        foreach ($entries as $entry) {
            $targets = [[$entry['owner'], $entry['owner_id']]];
            if ($entry['company_id'] !== null) {
                $targets[] = ['company', $entry['company_id']];
            }

            foreach ($targets as [$type, $legacyId]) {
                $id = ($type === 'contact' ? $contacts : $companies)->get($legacyId);
                $model = $type === 'contact' ? Contact::class : Company::class;

                if ($id === null || ! $this->hasItem((new $model)->getMorphClass(), $id, $entry['address'])) {
                    $missing++;

                    continue 2;
                }
            }
        }

        $imported = count($entries);

        return ['source' => $this->total, 'skipped' => $this->total - $imported, 'imported' => $imported - $missing, 'missing' => $missing];
    }

    /** @param  array<string, ?string>  $address */
    private function hasItem(string $morph, int $id, array $address): bool
    {
        $items = $this->itemKeys[$morph.'#'.$id] ??= Address::query()
            ->where('owner_type', $morph)->where('owner_id', $id)->where('field', 'addresses')->where('kind', self::KIND)
            ->get()
            ->map(fn (Address $item): string => self::key(self::normalise($item->getAttributes())))
            ->all();

        return in_array(self::key($address), $items, true);
    }

    /** @return list<array{id: int, owner: string, owner_id: int, address: array<string, ?string>, company_id: ?int}> */
    private function entries(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        $this->entries = [];

        if (! $this->exists()) {
            return $this->entries;
        }

        $schema = $this->legacy->getSchemaBuilder();
        $this->total = $this->legacy->table(self::TABLE)->count();

        $owners = [];
        foreach (self::OWNERS as $type => $table) {
            $owners[$type] = $schema->hasTable($table) ? array_flip($this->legacy->table($table)->pluck('id')->map(fn ($id): int => (int) $id)->all()) : [];
        }

        // Active companies by lower-cased trimmed name: the ids that share it.
        $byName = [];
        if ($schema->hasTable('company_data_1')) {
            foreach ($this->legacy->table('company_data_1')->where('active', 1)->get(['id', 'f_company_name']) as $company) {
                $name = mb_strtolower(trim((string) $company->f_company_name));
                if ($name !== '') {
                    $byName[$name][] = (int) $company->id;
                }
            }
        }

        foreach ($this->legacy->table(self::TABLE)->where('active', 1)->orderBy('id')->get() as $row) {
            $address = self::normalise(array_combine(self::FIELDS, array_map(fn (string $f) => $row->{"f_{$f}"} ?? null, self::FIELDS)));

            if (! Address::isAddress($address)) {
                continue;
            }

            $type = mb_strtolower(trim((string) $row->f_record_type));
            $ownerId = (int) $row->f_record_id;

            if (! isset($owners[$type][$ownerId])) {
                $this->warnings[] = "multiple address #{$row->id}: its owner {$type}#{$ownerId} doesn't exist, skipped";

                continue;
            }

            $name = trim((string) ($row->f_company_name ?? ''));
            $companyId = null;

            if ($name !== '') {
                $matches = $byName[mb_strtolower($name)] ?? [];

                if (count($matches) === 1) {
                    $companyId = $matches[0];
                } elseif ($matches === []) {
                    $this->warnings[] = "multiple address #{$row->id}: company \"{$name}\" matches no active company, not copied to a company";
                } else {
                    $this->warnings[] = "multiple address #{$row->id}: company \"{$name}\" matches ".count($matches).' active companies, not copied to a company';
                }
            }

            $this->entries[] = ['id' => (int) $row->id, 'owner' => $type, 'owner_id' => $ownerId, 'address' => $address, 'company_id' => $companyId];
        }

        return $this->entries;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, ?string>
     */
    private static function normalise(array $values): array
    {
        $address = [];

        foreach (self::FIELDS as $field) {
            $value = trim((string) ($values[$field] ?? ''));
            $address[$field] = $value === '' ? null : $value;
        }

        return $address;
    }

    /** @param  array<string, ?string>  $address */
    private static function key(array $address): string
    {
        return json_encode(array_map(fn (?string $v): ?string => $v === null ? null : mb_strtolower($v), $address));
    }
}
