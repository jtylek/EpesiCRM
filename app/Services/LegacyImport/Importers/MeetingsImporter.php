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
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Legacy `crm_meeting` recordset -> Epesi\Modules\CRM\Meetings\Models\Meeting. Must run after
 * CompaniesImporter and ContactsImporter. See TasksImporter's docblock for
 * how `company/<id>` Customers entries resolve into `customerCompanies`.
 *
 * Two format quirks confirmed live rather than assumed: `f_time` is a full
 * datetime with an epoch date (`1970-01-01 12:00:00`), only the time part
 * is real — Meeting's own `time` column expects just that; and `f_duration`
 * is stored in **seconds** (`7200` = 2 hours), while this app's
 * `duration_minutes` column, as the name says, wants minutes.
 */
class MeetingsImporter extends Importer
{
    private LegacyIdMap $contacts;

    private LegacyIdMap $companies;

    public function __construct()
    {
        parent::__construct();
        $this->contacts = LegacyIdMap::for(Contact::class);
        $this->companies = LegacyIdMap::for(Company::class);
    }

    public function legacyTab(): string
    {
        return 'crm_meeting';
    }

    public function modelClass(): string
    {
        return Meeting::class;
    }

    public function logName(): string
    {
        return 'meeting';
    }

    protected function trackedFields(): array
    {
        return [
            'title' => 'title',
            'date' => 'date',
            'time' => 'time',
            'duration' => 'duration_minutes',
            'description' => 'description',
            'status' => 'status',
            'priority' => 'priority',
            'permission' => 'permission',
        ];
    }

    protected function decodeTrackedValue(string $legacyField, ?string $raw): array
    {
        $column = $this->trackedFields()[$legacyField];

        return match ($legacyField) {
            'status' => [$column => $raw !== null && $raw !== '' ? (int) $raw : RecordStatus::Open->value],
            'priority' => [$column => $raw !== null && $raw !== '' ? (int) $raw : RecordPriority::Medium->value],
            'permission' => [$column => $raw !== null && $raw !== '' ? (int) $raw : RecordPermission::Public->value],
            'time' => [$column => $raw !== null && $raw !== '' ? Carbon::parse($raw)->format('H:i:s') : '00:00:00'],
            'duration' => [$column => $raw !== null && $raw !== '' ? intdiv((int) $raw, 60) : null],
            default => [$column => $raw !== '' ? $raw : null],
        };
    }

    protected function syncPivots(object $row, Model $model): void
    {
        $employeeIds = $this->contacts->getMany(array_map(
            fn (string $id) => (int) $id,
            LegacyValue::multi($row->f_employees ?? null)
        ));
        $model->employees()->sync($employeeIds);

        $customerRefs = LegacyValue::typedRefMulti($row->f_customers ?? null);

        $customerIds = $this->contacts->getMany(array_map(
            fn (array $ref) => $ref['id'],
            array_filter($customerRefs, fn (array $ref) => $ref['type'] === 'contact')
        ));
        $model->customers()->sync($customerIds);

        $customerCompanyIds = $this->companies->getMany(array_map(
            fn (array $ref) => $ref['id'],
            array_filter($customerRefs, fn (array $ref) => $ref['type'] === 'company')
        ));
        $model->customerCompanies()->sync($customerCompanyIds);
    }
}
