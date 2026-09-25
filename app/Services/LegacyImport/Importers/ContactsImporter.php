<?php

namespace App\Services\LegacyImport\Importers;

use App\Enums\RecordPermission;
use App\Models\User;
use App\Services\LegacyImport\Importer;
use App\Services\LegacyImport\LegacyIdMap;
use App\Services\LegacyImport\LegacyValue;
use Epesi\Modules\CommonData\Facades\CommonData;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
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

    protected function uniqueColumns(): array
    {
        return ['email'];
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

    protected function decodeTrackedValue(string $legacyField, ?string $raw): array
    {
        $column = $this->trackedFields()[$legacyField];

        return match ($legacyField) {
            'group' => [$column => $this->groupKeys(array_map(
                fn (string $key): string => self::GROUP_KEY_REMAP[$key] ?? $key,
                LegacyValue::multi($raw)
            ), 'Contacts_Groups')],
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

    /**
     * Group keys, filtered to those the shared list actually offers — what the
     * enum's tryFrom() did before the list moved into CommonData.
     *
     * An empty list means commondata has not been imported yet (it is the first
     * tab of `import:legacy all` for exactly this reason). Filtering against
     * nothing would silently drop every group, so pass the keys through
     * untouched instead and let a later commondata import make them resolve.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function groupKeys(array $keys, string $list): array
    {
        $known = CommonData::array($list);

        if ($known === []) {
            return array_values($keys);
        }

        return array_values(array_filter($keys, fn (string $key): bool => array_key_exists($key, $known)));
    }
}
