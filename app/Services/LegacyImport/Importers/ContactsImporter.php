<?php

namespace App\Services\LegacyImport\Importers;

use App\Enums\RecordPermission;
use App\Models\User;
use App\Services\LegacyImport\Importer;
use App\Services\LegacyImport\LegacyIdMap;
use App\Services\LegacyImport\LegacyValue;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Models\Address;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Legacy `contact` recordset -> Epesi\Modules\CRM\Contacts\Models\Contact. Must run after
 * UsersImporter (resolves f_login -> user_id) and CompaniesImporter
 * (resolves f_company_name -> company_id, f_related_companies -> the
 * company_contact pivot).
 *
 * Contacts_Groups' legacy key for the "Customer" group is the historical
 * typo "custm" (see ContactsInstall.php line 109); this port spells it out as
 * "customer", so that one key needs an explicit remap here. CommonDataImporter
 * applies the same rename to the shared list itself — the values stored on a
 * contact and the list they are read against have to agree, so the two maps
 * are kept deliberately in step.
 *
 * Epesi's Login/Username/Set Password/Admin/Access block was redesigned in
 * Phase 1, not translated field-for-field (see Contact's own docblock): the
 * Contacts/Access CommonData keys ('manager'/'employee') already match this
 * app's Spatie role names 1:1, assigned to the linked User in afterSave().
 */
class ContactsImporter extends Importer
{
    private LegacyIdMap $companies;

    private LegacyIdMap $users;

    private const GROUP_KEY_REMAP = ['custm' => 'customer'];

    /** Column prefix => the kind its address becomes, in order. */
    private const ADDRESSES = ['' => 'business', 'home_' => 'home'];

    private const ADDRESS_FIELDS = ['address_1', 'address_2', 'city', 'country', 'zone', 'postal_code'];

    /** Legacy field => the kind its number becomes, in order: the work number is the primary one. */
    private const PHONES = ['work_phone' => 'work', 'mobile_phone' => 'mobile', 'home_phone' => 'home', 'fax' => 'fax'];

    public function __construct()
    {
        parent::__construct();
        $this->companies = LegacyIdMap::for(Company::class);
        $this->users = LegacyIdMap::for(User::class);
    }

    public function legacyTab(): string
    {
        return 'contact';
    }

    public function modelClass(): string
    {
        return Contact::class;
    }

    public function logName(): string
    {
        return 'contact';
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
            'last_name' => 'last_name',
            'first_name' => 'first_name',
            'company_name' => 'company_id',
            'memo' => 'memo',
            'group' => 'groups',
            'title' => 'title',
            'work_phone' => 'work_phone',
            'mobile_phone' => 'mobile_phone',
            'fax' => 'fax',
            'email' => 'email',
            'web_address' => 'web_address',
            'address_1' => 'address_1',
            'address_2' => 'address_2',
            'city' => 'city',
            'country' => 'country',
            'zone' => 'zone',
            'postal_code' => 'postal_code',
            'permission' => 'permission',
            'home_phone' => 'home_phone',
            'home_address_1' => 'home_address_1',
            'home_address_2' => 'home_address_2',
            'home_city' => 'home_city',
            'home_country' => 'home_country',
            'home_zone' => 'home_zone',
            'home_postal_code' => 'home_postal_code',
            'login' => 'user_id',
        ];
    }

    /** Both addresses, the phones, the e-mail and the web address are collection items now, not columns. */
    protected function historyOnlyColumns(): array
    {
        $columns = [...array_keys(self::PHONES), 'web_address', 'email'];

        foreach (array_keys(self::ADDRESSES) as $prefix) {
            foreach (self::ADDRESS_FIELDS as $field) {
                $columns[] = $prefix.$field;
            }
        }

        return $columns;
    }

    /**
     * The main address as a Business item, then the home one as Home, each
     * when it has a street, a city or a postal code (Address::isAddress());
     * the work, mobile and home numbers and the fax as phone numbers of
     * those kinds; the web address as an online account of kind Website.
     */
    protected function collections(object $row): array
    {
        $addresses = [];

        foreach (self::ADDRESSES as $prefix => $kind) {
            $address = [];

            foreach (self::ADDRESS_FIELDS as $field) {
                $value = trim((string) ($row->{"f_{$prefix}{$field}"} ?? ''));
                $address[$field] = $value === '' ? null : $value;
            }

            if (Address::isAddress($address)) {
                $addresses[] = ['kind' => $kind, ...$address];
            }
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
            ...$this->legacyExtraEmails($row, 'contact'),
        ]));

        return [
            'addresses' => $addresses,
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
            // Filtered against legacy's list, so under legacy's keys; renamed after.
            'group' => [$column => array_map(
                fn (string $key): string => self::GROUP_KEY_REMAP[$key] ?? $key,
                $this->commonDataKeys(LegacyValue::multi($raw), 'Contacts_Groups')
            )],
            'permission' => [$column => $raw !== null && $raw !== '' ? (int) $raw : RecordPermission::Public->value],
            'company_name' => [$column => $raw !== null && $raw !== '' ? $this->companies->get((int) $raw) : null],
            'login' => [$column => $raw !== null && $raw !== '' ? $this->users->get((int) $raw) : null],
            'email' => [$column => $raw !== '' ? $raw : null],
            default => [$column => $raw !== '' ? $raw : null],
        };
    }

    protected function syncPivots(object $row, Model $model): void
    {
        $companyIds = $this->companies->getMany(array_map(
            fn (string $id) => (int) $id,
            LegacyValue::multi($row->f_related_companies ?? null)
        ));

        $model->relatedCompanies()->sync($companyIds);
    }

    /**
     * Contacts/Access ('manager'/'employee') matches this app's role names
     * directly; user_login.admin (0/1/2 = No/Administrator/Super
     * Administrator — Base_AclCommon's own levels, confirmed via
     * ContactsCommon_0.php's QFfield_admin) additionally grants super_admin
     * at level 2. A linked login with no Access selection and no super_admin
     * level still needs at least one role to pass User::canAccessPanel(),
     * so it defaults to 'employee' — the baseline every other imported
     * login effectively already has.
     */
    protected function afterSave(object $row, Model $model): void
    {
        if (! $model->user_id) {
            return;
        }

        $user = User::find($model->user_id);
        if (! $user) {
            return;
        }

        $roles = array_values(array_filter(
            array_map(fn (string $key) => in_array($key, ['manager', 'employee'], true) ? $key : null, LegacyValue::multi($row->f_access ?? null))
        ));

        $adminLevel = (int) (DB::connection('legacy')->table('user_login')->where('id', $row->f_login)->value('admin') ?? 0);
        if ($adminLevel === 2) {
            $roles[] = 'super_admin';
        }

        if ($roles === []) {
            $roles[] = 'employee';
        }

        $user->syncRoles($roles);
    }
}
