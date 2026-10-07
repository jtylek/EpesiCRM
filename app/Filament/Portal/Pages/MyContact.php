<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Filament\Concerns\UsesEpesiFormLayout;
use App\Support\Auth\PortalEmails;
use BackedEnum;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Models\Address;
use Epesi\Modules\RecordBrowser\Models\OnlineAccount;
use Epesi\Modules\RecordBrowser\Models\PhoneNumber;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * The customer portal's only page (see AI-shared/Customer-portal.md and
 * PortalPanelProvider): the signed-in customer's own contact, personal
 * details editable, nothing else. Never takes a record id — always
 * `Auth::user()->contact` — so there's no URL to change to reach anyone
 * else's.
 *
 * Opens in View mode, the same convention every other resource in this port
 * follows (AI-shared/conventions.md: "View before Edit, everywhere") —
 * "Edit" switches to the form, "Save" (or "Cancel") switches back, rather
 * than staying on the form after saving.
 *
 * Company, Permission, Groups, Related Companies, Memo and the linked login
 * (`user_id`) are deliberately not on the form at all, not merely disabled:
 * `$data` never holds a key for them, so there's nothing for a customer to
 * post that would change them. Staff still manage those from the Contacts
 * resource in the main panel.
 *
 * Built like Administration → Mail Server (App\Filament\Administration\Pages\MailServer)
 * rather than on the RecordBrowser engine: one record, no list, no create, no
 * delete.
 */
class MyContact extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;
    use UsesEpesiFormLayout;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'My Contact';

    protected static ?string $title = 'My Contact';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** Public so Livewire keeps it between requests (mount() only runs once). */
    public Contact $contact;

    /** View by default; Edit swaps this page's own content, not a separate route. */
    public bool $editing = false;

    public function mount(): void
    {
        $this->contact = Auth::user()?->contact ?? abort(404);
    }

    /**
     * Both the read-only entries and the form are always declared, each
     * shown or hidden by its own `visible()` — as the header actions are
     * (see getHeaderActions()) — rather than swapped for one another by a
     * condition here: on this page (Livewire actions rebuilding the page
     * mid-request, not two different routes/pages the way a real
     * ViewRecord/EditRecord pair would be), only what a component's own
     * `visible()` says is reliably re-evaluated on every render.
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->record($this->contact)
            ->components([
                Section::make(__('My Contact'))
                    ->description($this->contact->company
                        ? __('Company: :company', ['company' => $this->contact->company->company_name])
                        : null)
                    ->columns(2)
                    ->schema([
                        // A closure, not the value itself: Filament resolves a
                        // page's schemas once early in the request and reuses
                        // that build for the final render, so a plain value
                        // captured here would still show what $this->contact
                        // held before save()'s update() changed it, not after.
                        // A collection reads its items off the record itself.
                        ...array_map(
                            fn (Field $field): mixed => ($field->type->hasColumn()
                                ? $field->toInfolistEntry()->state(fn (): mixed => $this->contact->getAttribute($field->name))
                                : $field->toInfolistEntry())
                                ->visible(fn (): bool => ! $this->editing),
                            $this->fields(),
                        ),
                        EmbeddedSchema::make('form')
                            ->columnSpanFull()
                            ->visible(fn (): bool => $this->editing),
                    ]),
                // Not part of the form: each change is made at once (and a new
                // address needs verifying), since one of them is the login.
                Section::make(__('E-mail addresses'))
                    ->description(__('The Primary address is the one you sign in with. A new address has to be verified, with the link e-mailed to it, before it can become the Primary one.'))
                    ->headerActions([$this->addEmailAction()])
                    ->schema([
                        View::make('filament.portal.emails')->viewData(fn (): array => ['rows' => $this->emailRows()]),
                    ]),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        $field = fn (Field $field): mixed => $field->toFormComponent();

        // The record, so the collections load from and save to it.
        return $schema
            ->model($this->contact)
            ->statePath('data')
            ->columns(2)
            ->components(array_map($field, $this->fields()));
    }

    /**
     * @return array<int, Field>
     */
    protected function fields(): array
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

    /**
     * The fields held in the contact's own columns: the form fills and saves
     * the collections itself.
     *
     * @return array<int, string>
     */
    protected function fieldNames(): array
    {
        return array_values(array_map(
            fn (Field $field): string => $field->name,
            array_filter($this->fields(), fn (Field $field): bool => $field->type->hasColumn()),
        ));
    }

    /**
     * All three are always registered — Filament caches the actions a page
     * declares once, rather than re-collecting a changed return value here,
     * so which one shows for the current mode is each action's own
     * `visible()`, not which ones this method returns.
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->editAction(),
            $this->saveAction(),
            $this->cancelAction(),
        ];
    }

    protected function editAction(): Action
    {
        return Action::make('edit')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencil)
            ->visible(fn (): bool => ! $this->editing)
            ->action(function (): void {
                $this->form->fill($this->contact->only($this->fieldNames()));
                $this->editing = true;
            });
    }

    protected function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->color('gray')
            ->visible(fn (): bool => $this->editing)
            ->action(fn () => $this->editing = false);
    }

    protected function saveAction(): Action
    {
        return Action::make('save')
            ->label('Save')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->visible(fn (): bool => $this->editing)
            ->action(function (): void {
                Gate::authorize('update', $this->contact);

                $this->contact->update($this->form->getState());
                $this->editing = false;

                Notification::make()->title(__('Saved'))->success()->send();
            });
    }

    /** @return array<int, array{id: int, value: string, primary: bool, verified: bool}> */
    public function emailRows(): array
    {
        return PortalEmails::rows(Auth::user(), $this->contact)->all();
    }

    protected function addEmailAction(): Action
    {
        return Action::make('addEmail')
            ->label(__('Add e-mail address'))
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading(__('Add e-mail address'))
            ->modalDescription(__('A link to verify it will be e-mailed to this address.'))
            ->modalSubmitActionLabel(__('Add and send link'))
            ->schema([
                TextInput::make('email')
                    ->label(__('E-mail'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                        if ($reason = PortalEmails::whyNotAdd(Auth::user(), (string) $value)) {
                            $fail($reason);
                        }
                    }),
            ])
            ->action(function (array $data): void {
                Gate::authorize('update', $this->contact);

                $this->notifySent(PortalEmails::add(Auth::user(), $this->contact, $data['email']), $data['email']);
            });
    }

    public function resendEmailVerificationAction(): Action
    {
        return Action::make('resendEmailVerification')
            ->label(__('Send link again'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->link()
            ->action(function (array $arguments): void {
                Gate::authorize('update', $this->contact);

                $item = $this->contact->collection('emails')->find($arguments['id'] ?? null);

                if ($item === null || $item->verified_at !== null) {
                    return;
                }

                $this->notifySent(PortalEmails::sendVerification($item), $item->value);
            });
    }

    public function makeEmailPrimaryAction(): Action
    {
        return Action::make('makeEmailPrimary')
            ->label(__('Make Primary'))
            ->icon(Heroicon::OutlinedStar)
            ->link()
            ->modalHeading(__('Make Primary'))
            ->modalDescription(__('This address becomes the one you sign in with. Enter your password to confirm.'))
            ->schema([
                TextInput::make('password')
                    ->label(__('Password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->currentPassword(),
            ])
            ->action(function (array $arguments): void {
                Gate::authorize('update', $this->contact);

                $why = PortalEmails::makePrimary(Auth::user(), $this->contact, (int) ($arguments['id'] ?? 0));

                $why === null
                    ? Notification::make()->title(__('Saved'))->success()->send()
                    : Notification::make()->title($why)->danger()->send();
            });
    }

    public function removeEmailAction(): Action
    {
        return Action::make('removeEmail')
            ->label(__('Remove'))
            ->icon(Heroicon::OutlinedTrash)
            ->link()
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                Gate::authorize('update', $this->contact);

                PortalEmails::remove(Auth::user(), $this->contact, (int) ($arguments['id'] ?? 0))
                    ? Notification::make()->title(__('Saved'))->success()->send()
                    : Notification::make()->title(__('The Primary address can not be removed.'))->danger()->send();
            });
    }

    protected function notifySent(string $status, string $address): void
    {
        $status === PortalEmails::SENT
            ? Notification::make()->title(__('A link to verify :email was sent', ['email' => $address]))->success()->send()
            : Notification::make()->title(__('The e-mail could not be sent. Try again later.'))->danger()->send();
    }
}
