<?php

namespace Epesi\Modules\CRM\Contacts\Setup;

use App\Enums\RecordPermission;
use App\Models\User;
use App\Support\AddressFields;
use App\Support\Setup\SetupStep;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Str;

/**
 * CRM_ContactsInstall::post_install(): the company this CRM belongs to, and
 * the administrator's own contact in it — which is what links the login to a
 * person everywhere a contact is shown (User::contact()).
 */
class YourCompanyStep implements SetupStep
{
    public function label(): string
    {
        return __('Your company');
    }

    public function description(): ?string
    {
        return __('Your own company and your name, as they will appear in the CRM.');
    }

    public function schema(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('company_name')->label('Company name')->required()->maxLength(64),
                TextInput::make('short_name')->label('Short name')->maxLength(64),
                TextInput::make('first_name')->label('Your first name')->required()->maxLength(64),
                TextInput::make('last_name')->label('Your last name')->required()->maxLength(64),
            ]),
            Section::make(__('Address and contact details'))->compact()->collapsible()->columns(2)->schema([
                TextInput::make('address_1')->label('Address 1')->maxLength(64),
                TextInput::make('address_2')->label('Address 2')->maxLength(64),
                TextInput::make('city')->maxLength(64),
                TextInput::make('postal_code')->label('Postal code')->maxLength(64),
                AddressFields::country()->toFormComponent(),
                AddressFields::zone()->toFormComponent(),
                TextInput::make('phone')->tel()->maxLength(64),
                TextInput::make('fax')->tel()->maxLength(64),
                TextInput::make('web_address')->label('Web address')->url()->maxLength(255),
            ]),
        ];
    }

    /**
     * The administrator's name split at the last space — a guess to correct,
     * not a rule.
     */
    public function defaults(): array
    {
        $name = trim((string) auth()->user()?->name);

        return str_contains($name, ' ')
            ? ['first_name' => Str::beforeLast($name, ' '), 'last_name' => Str::afterLast($name, ' ')]
            : ['first_name' => $name];
    }

    public function handle(array $data, User $admin): void
    {
        $address = collect($data)->only(['address_1', 'address_2', 'city', 'postal_code', 'country', 'zone'])->all();

        $company = Company::create([
            ...$address,
            'company_name' => $data['company_name'],
            'short_name' => $data['short_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'fax' => $data['fax'] ?? null,
            'web_address' => $data['web_address'] ?? null,
            'permission' => RecordPermission::Public,
        ]);

        // Update rather than add when the administrator already has a
        // contact — the page can be reached again after a failed save.
        $contact = Contact::query()->withoutGlobalScopes()->firstOrNew(['user_id' => $admin->id]);
        $contact->fill([
            ...$address,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'company_id' => $company->id,
            'email' => $contact->email ?? $admin->email,
            'permission' => RecordPermission::Public,
        ]);
        $contact->forceFill(['user_id' => $admin->id])->save();
    }
}
