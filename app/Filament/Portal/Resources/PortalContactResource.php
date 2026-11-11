<?php

namespace App\Filament\Portal\Resources;

use App\Filament\Portal\Pages\MyContact;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Models\Address;
use Epesi\Modules\RecordBrowser\Models\OnlineAccount;
use Epesi\Modules\RecordBrowser\Models\PhoneNumber;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The portal's deliberately narrow view of a contact. It uses the same
 * RecordBrowser schemas as the staff Contact resource, but exposes only the
 * contact belonging to the signed-in portal user.
 */
class PortalContactResource extends RecordsetResource
{
    protected static ?string $model = Contact::class;

    protected static ?string $slug = 'my-contact';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->contact !== null;
    }

    public static function canView(Model $record): bool
    {
        return Auth::user()?->contact?->is($record) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canView($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * There is no list page in the portal: the resource's sole page is the
     * signed-in user's own contact. RecordBrowser's standard breadcrumbs ask
     * for an index URL, so point that conventional location at the same page.
     *
     * @param  array<mixed>  $parameters
     */
    public static function getIndexUrl(
        array $parameters = [],
        bool $isAbsolute = true,
        ?string $panel = null,
        ?Model $tenant = null,
        bool $shouldGuessMissingParameters = false,
    ): string {
        return MyContact::getUrl([], $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters);
    }

    public static function fields(): array
    {
        return [
            Field::text('last_name')->required()->maxLength(64),
            Field::text('first_name')->required()->maxLength(64),
            Field::text('title')->maxLength(64),
            Field::collection('phones', PhoneNumber::class)->label('Phone numbers'),
            Field::collection('online_accounts', OnlineAccount::class)->label('Online accounts'),
            Field::collection('addresses', Address::class),
        ];
    }

    public static function getPages(): array
    {
        return [
            'view' => MyContact::route('/'),
        ];
    }
}
