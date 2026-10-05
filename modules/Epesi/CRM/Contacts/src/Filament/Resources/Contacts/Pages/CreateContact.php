<?php

namespace Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages;

use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\ContactResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateContact extends CreateRecord
{
    protected static string $resource = ContactResource::class;

    /**
     * "Create company" (see ContactResource::extendForm()): the company is made
     * first so the contact can point at it, with the contact's permission and
     * addresses. Both inputs are undehydrated, so they're read from the form's
     * raw state.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! ($this->data['create_company'] ?? false) || blank($name = trim((string) ($this->data['new_company_name'] ?? ''))) || ! Gate::allows('create', Company::class)) {
            return $data;
        }

        $company = DB::transaction(function () use ($data, $name): Company {
            $company = Company::create([
                'company_name' => $name,
                'permission' => $data['permission'] ?? null,
            ]);

            $company->syncCollection('addresses', array_map(
                fn (array $address): array => array_diff_key($address, ['id' => true]),
                array_values((array) ($this->data['addresses'] ?? [])),
            ));

            return $company;
        });

        $data['company_id'] = $company->getKey();

        return $data;
    }
}
