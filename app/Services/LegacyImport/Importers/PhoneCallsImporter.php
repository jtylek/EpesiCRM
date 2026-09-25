<?php

namespace App\Services\LegacyImport\Importers;

use App\Enums\RecordPermission;
use App\Enums\RecordPriority;
use App\Enums\RecordStatus;
use App\Services\LegacyImport\Importer;
use App\Services\LegacyImport\LegacyIdMap;
use App\Services\LegacyImport\LegacyValue;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Illuminate\Database\Eloquent\Model;

/**
 * Legacy `phonecall` recordset -> Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall. Must run after
 * CompaniesImporter and ContactsImporter (Customer can reference either).
 *
 * Customer/Other-Customer/Phone are read together here as one composite,
 * import-only mapping (not history-replayed — see Importer's docblock):
 * Epesi's Customer field is dual-recordset (contact/99 or company/99) and
 * legacy Phone is an index (1=Mobile, 2=Work, 3=Home, 4=the *company's* own
 * Phone) into whichever Contact/Company Customer resolved to, not a stored
 * number — see PhoneCallCommon_0.php's `case 1/2/3/4` switch, confirmed live
 * (every sample row here has "Other Customer"/"Other Phone" both unset and a
 * plain Customer + phone-index pair). A `company/<id>` Customer now resolves
 * into the real `company_id` FK (add_customer_companies_to_activities)
 * rather than being faked as an Other-Customer text entry — the previous
 * approach, from when PhoneCall had nowhere else to put a company link.
 */
class PhoneCallsImporter extends Importer
{
    private LegacyIdMap $companies;

    private LegacyIdMap $contacts;

    public function __construct()
    {
        parent::__construct();
        $this->companies = LegacyIdMap::for(Company::class);
        $this->contacts = LegacyIdMap::for(Contact::class);
    }

    public function legacyTab(): string
    {
        return 'phonecall';
    }

    public function modelClass(): string
    {
        return PhoneCall::class;
    }

    public function logName(): string
    {
        return 'phone_call';
    }

    protected function trackedFields(): array
    {
        return [
            'subject' => 'subject',
            'description' => 'description',
            'status' => 'status',
            'priority' => 'priority',
            'permission' => 'permission',
            'date_and_time' => 'called_at',
        ];
    }

    protected function decodeTrackedValue(string $legacyField, ?string $raw): array
    {
        $column = $this->trackedFields()[$legacyField];

        return match ($legacyField) {
            'status' => [$column => $raw !== null && $raw !== '' ? (int) $raw : RecordStatus::Open->value],
            'priority' => [$column => $raw !== null && $raw !== '' ? (int) $raw : RecordPriority::Medium->value],
            'permission' => [$column => $raw !== null && $raw !== '' ? (int) $raw : RecordPermission::Public->value],
            'date_and_time' => [$column => $raw !== null && $raw !== '' ? $raw : now()],
            default => [$column => $raw !== '' ? $raw : null],
        };
    }

    protected function extraAttributes(object $row): array
    {
        $ref = LegacyValue::typedRef($row->f_customer ?? null);
        $contactId = null;
        $companyId = null;
        $otherCustomer = (bool) ($row->f_other_customer ?? false);
        $otherCustomerName = $row->f_other_customer_name ?: null;
        $companyPhone = null;

        if ($ref && $ref['type'] === 'contact') {
            $contactId = $this->contacts->get($ref['id']);
        } elseif ($ref && $ref['type'] === 'company') {
            $companyId = $this->companies->get($ref['id']);
            $companyPhone = $companyId ? Company::withTrashed()->find($companyId)?->phone : null;
        }

        $phoneNumber = null;
        if ($otherCustomer && ($row->f_other_phone ?? false)) {
            $phoneNumber = $row->f_other_phone_number ?: null;
        } elseif ($row->f_phone !== null && $row->f_phone !== '') {
            $contact = $contactId ? Contact::withTrashed()->find($contactId) : null;
            $phoneNumber = match ((int) $row->f_phone) {
                1 => $contact?->mobile_phone,
                2 => $contact?->work_phone,
                3 => $contact?->home_phone,
                4 => $companyPhone,
                default => null,
            };
        }

        return [
            'contact_id' => $contactId,
            'company_id' => $companyId,
            'other_customer' => $otherCustomer,
            'other_customer_name' => $otherCustomerName,
            'phone_number' => $phoneNumber,
        ];
    }

    protected function syncPivots(object $row, Model $model): void
    {
        $employeeIds = $this->contacts->getMany(array_map(
            fn (string $id) => (int) $id,
            LegacyValue::multi($row->f_employees ?? null)
        ));

        $model->employees()->sync($employeeIds);
    }
}
