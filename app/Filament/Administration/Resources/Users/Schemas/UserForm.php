<?php

namespace App\Filament\Administration\Resources\Users\Schemas;

use App\Models\User;
use Closure;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

/**
 * Shared by both CreateUser and EditUser (via UserResource::form()).
 *
 * A login is made for a person who is already a Contact: creating one means
 * choosing that contact, whose name and e-mail address the user takes over
 * (CreateUser links the two). A contact with no e-mail address can't be
 * chosen: the address is the sign-in name and where the link to choose a
 * password goes. Editing shows the account's own name and e-mail address.
 *
 * Password is set here only on create, and optional: left blank, CreateUser
 * gives the account a random one and e-mails the user a link to choose their
 * own (legacy Epesi generated a password and e-mailed it). Editing a user's
 * password happens through the "Reset Password" header action on ViewUser
 * instead (matching ContactLoginEntries::resetPasswordAction() for a
 * Contact's linked login), so an Edit visit never shows a blank password
 * field that could be mistaken for "leave blank to keep current".
 */
class UserForm
{
    /** How many contacts the search offers at once. */
    protected const SEARCH_LIMIT = 50;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        self::contactSelect()
                            ->visible(fn (string $operation): bool => $operation === 'create'),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->visible(fn (string $operation): bool => $operation !== 'create'),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            // The contact's: CreateUser takes it from the contact again.
                            ->readOnly(fn (string $operation): bool => $operation === 'create')
                            ->helperText(fn (string $operation): ?string => $operation === 'create' ? __('The contact\'s e-mail address. To change it, change it on the contact.') : null),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->confirmed()
                            ->helperText(__('Leave blank to e-mail them a link to choose their own password.'))
                            ->visible(fn (string $operation): bool => $operation === 'create'),
                        TextInput::make('password_confirmation')
                            ->password()
                            ->revealable()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'create'),
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->rule(fn (?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                if ($reason = self::whySuperAdminStays($record, (array) $value)) {
                                    $fail($reason);
                                }
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Why a save that leaves $roleIds on $user can't go through, or null:
     * nobody takes super_admin from their own account, so no one can lock
     * themselves out of the Administration panel (which only a super_admin
     * opens) with a click — and since only a super_admin gets here, another
     * one always remains. A Policy can't say this: super_admin passes every
     * Gate check (AI-shared/User-management.md).
     *
     * @param  array<int, mixed>  $roleIds
     */
    public static function whySuperAdminStays(?Model $user, array $roleIds): ?string
    {
        if (! $user instanceof User || ! $user->hasRole('super_admin')) {
            return null;
        }

        $superAdmin = Role::query()->where('name', 'super_admin')->first();

        if ($superAdmin === null || in_array($superAdmin->getKey(), array_map('intval', $roleIds), true)) {
            return null;
        }

        return $user->is(Auth::user())
            ? __('You cannot remove super_admin from your own account.')
            : null;
    }

    /**
     * The contact the login is for. Not a column of users (the link is
     * contacts.user_id), so it isn't saved from here: CreateUser reads it.
     */
    protected static function contactSelect(): Select
    {
        return Select::make('contact_id')
            ->label('Contact')
            ->helperText(__('Who the login is for. Only a contact with an e-mail address and no login yet can be chosen.'))
            ->searchable()
            ->searchPrompt(__('Type a name or an e-mail address'))
            ->noSearchResultsMessage(__('No contact without a login matches.'))
            ->getSearchResultsUsing(fn (string $search): array => self::search($search))
            ->getOptionLabelUsing(fn (mixed $value): ?string => ($contact = Contact::query()->find($value)) ? self::label($contact) : null)
            ->required()
            ->live()
            ->dehydrated(false)
            ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                $contact = Contact::query()->find($value);

                if ($contact === null) {
                    $fail(__('This contact no longer exists.'));
                } elseif (blank($contact->primaryEmail())) {
                    $fail(__('This contact has no e-mail address, so it can\'t be made a user.'));
                } elseif ($contact->user_id !== null) {
                    $fail(__('This contact already has a login.'));
                }
            }])
            ->afterStateUpdated(function (mixed $state, Set $set): void {
                $contact = filled($state) ? Contact::query()->find($state) : null;
                $email = $contact?->primaryEmail();

                if ($contact !== null && blank($email)) {
                    Notification::make()
                        ->title(__(':name can\'t be made a user', ['name' => $contact->full_name ?: __('This contact')]))
                        ->body(__('This contact has no e-mail address. Add one to the contact first, then choose it again.'))
                        ->warning()
                        ->persistent()
                        ->send();

                    $set('contact_id', null);
                    $set('email', null);

                    return;
                }

                $set('email', $email);
            });
    }

    /**
     * Contacts that have no login yet, whose first name, last name or e-mail
     * address hold every word typed. One with no e-mail address is offered
     * too, marked so, and refused when chosen: better than not finding the
     * person and wondering why.
     *
     * @return array<int, string>
     */
    protected static function search(string $search): array
    {
        $query = Contact::query()->whereNull('user_id');

        foreach (preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $query->where(fn ($query) => $query
                ->where('first_name', 'like', "%{$word}%")
                ->orWhere('last_name', 'like', "%{$word}%")
                ->orWhereHas('emails', fn ($query) => $query->where('value', 'like', "%{$word}%")));
        }

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(self::SEARCH_LIMIT)
            ->get()
            ->mapWithKeys(fn (Contact $contact): array => [$contact->getKey() => self::label($contact)])
            ->all();
    }

    protected static function label(Contact $contact): string
    {
        return trim(($contact->full_name ?: '#'.$contact->getKey()).' · '.($contact->primaryEmail() ?: __('(no e-mail address)')));
    }
}
