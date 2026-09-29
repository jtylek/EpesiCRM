<?php

namespace App\Filament\Administration\Resources\Users\Pages;

use App\Filament\Administration\Resources\Users\UserResource;
use App\Support\Auth\NewAccountMailer;
use App\Support\Auth\UserActivity;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Filament\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** The contact the login is for (UserForm's Contact choice). */
    protected ?Contact $contact = null;

    /** Whether the administrator left the password blank, so the user has to be told how to sign in. */
    protected bool $passwordGenerated = false;

    /**
     * A login is made for a Contact: the user takes its name and its e-mail
     * address, both from the contact itself and not from what the form
     * posted, since the form only shows the address.
     *
     * Legacy Epesi: "If you leave password fields empty random password is
     * automatically generated and e-mailed to the user." The random password
     * is never shown or sent (the `hashed` cast stores only its hash); the
     * user gets a link to choose their own, in afterCreate().
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->contact = Contact::query()->findOrFail($this->data['contact_id'] ?? null);

        $email = $this->contact->primaryEmail();

        $data['name'] = $this->contact->full_name ?: $email;
        $data['email'] = $email;

        $this->passwordGenerated = blank($data['password'] ?? null);

        if ($this->passwordGenerated) {
            $data['password'] = Str::random(40);
        }

        return $data;
    }

    /**
     * The user and its link to the contact (contacts.user_id) are made
     * together: a login that belongs to no contact would have no name shown
     * anywhere but here.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $user = parent::handleRecordCreation($data);

            $this->contact->update(['user_id' => $user->getKey()]);

            return $user;
        });
    }

    protected function afterCreate(): void
    {
        // The History starts with who made the login, for whom, and with what roles.
        $this->record->unsetRelation('roles');
        UserActivity::contactLinked($this->record, $this->contact);
        UserActivity::rolesChanged($this->record, [], $this->record->getRoleNames()->all());

        if (! $this->passwordGenerated) {
            return;
        }

        if ($reason = NewAccountMailer::whyNoLink($this->record)) {
            Notification::make()
                ->title(__('The user was created, but no e-mail was sent'))
                ->body($reason.' '.__('Set a password for them with Reset Password.'))
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        if (NewAccountMailer::sendSetPasswordLink($this->record) === NewAccountMailer::SENT) {
            Notification::make()
                ->title(__('An e-mail with a link to choose a password was sent to :email', ['email' => $this->record->email]))
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('The user was created, but the e-mail could not be sent'))
            ->body(__('Check Administration → Mail Server. Until it works, set a password for them with Reset Password.'))
            ->warning()
            ->persistent()
            ->send();
    }
}
