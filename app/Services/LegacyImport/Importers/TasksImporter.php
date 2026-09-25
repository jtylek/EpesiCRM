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
use Epesi\Modules\CRM\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Model;

/**
 * Legacy `task` recordset -> Epesi\Modules\CRM\Tasks\Models\Task. Must run after
 * CompaniesImporter and ContactsImporter (Employees is contact-only,
 * Customers is dual-recordset — company OR contact).
 *
 * `company/<id>` entries in the legacy Customers multiselect resolve into
 * `customerCompanies` (task_customer_company), added alongside the
 * pre-existing contact-only `customers` pivot rather than replacing it —
 * see create_tasks_table's/add_customer_companies_to_activities's docblocks
 * for why this is two plain pivots, not one polymorphic one.
 *
 * Legacy `f_longterm` is deliberately ignored — Jasiek: won't be used. The
 * column existed briefly on the Laravel side too; see
 * drop_longterm_from_tasks_table's docblock.
 */
class TasksImporter extends Importer
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
        return 'task';
    }

    public function modelClass(): string
    {
        return Task::class;
    }

    public function logName(): string
    {
        return 'task';
    }

    protected function trackedFields(): array
    {
        return [
            'title' => 'title',
            'description' => 'description',
            'status' => 'status',
            'priority' => 'priority',
            'permission' => 'permission',
            'deadline' => 'deadline',
            'timeless' => 'timeless',
        ];
    }

    protected function decodeTrackedValue(string $legacyField, ?string $raw): array
    {
        $column = $this->trackedFields()[$legacyField];

        return match ($legacyField) {
            'status' => [$column => $raw !== null && $raw !== '' ? (int) $raw : RecordStatus::Open->value],
            'priority' => [$column => $raw !== null && $raw !== '' ? (int) $raw : RecordPriority::Medium->value],
            'permission' => [$column => $raw !== null && $raw !== '' ? (int) $raw : RecordPermission::Public->value],
            'timeless' => [$column => (bool) $raw],
            'deadline' => [$column => $raw !== '' ? $raw : null],
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
