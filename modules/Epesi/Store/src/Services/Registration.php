<?php

namespace Epesi\Modules\Store\Services;

use App\Models\User;
use App\Support\Version;
use Epesi\Modules\Store\Filament\Pages\Store;
use Epesi\Modules\Store\Models\StoreSetting;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use Throwable;

/**
 * This installation's registration with the Epesi Store. Registration is free
 * and optional; it is what the store asks for before it serves updates and the
 * module catalog.
 *
 * Nothing is ever pushed from the store: the installation starts with a
 * handshake (Register on About or Epesi Store), the administrator fills in the
 * store's form and confirms the e-mail, and the installation picks the result
 * up by asking — on those pages, on Refresh, every 15 minutes from cron while
 * pending, and in the daily check-in once registered.
 */
class Registration
{
    public function __construct(
        protected StoreClient $client,
        protected Diagnostics $diagnostics,
    ) {}

    public function setting(): StoreSetting
    {
        return StoreSetting::current();
    }

    /**
     * Introduces the installation to the store and returns the link to the
     * store's registration form, filled in with what epesi already knows.
     */
    public function start(?User $user = null): string
    {
        $setting = $this->setting();

        if (! $setting->hasIdentity()) {
            $setting->update([
                'instance_uuid' => (string) Str::uuid(),
                'instance_secret' => Str::random(64),
            ]);
        }

        $answer = $this->client->handshake($setting->refresh(), $this->prefill($user));
        $this->apply($answer);

        return (string) $answer['register_url'];
    }

    /** Asks the store for the registration's state (cheap; no diagnostics). */
    public function refreshStatus(): StoreSetting
    {
        $setting = $this->setting();

        if ($setting->hasIdentity()) {
            $this->guard(fn () => $this->apply($this->client->status()));
        }

        return $setting->refresh();
    }

    /**
     * The daily call: registration state, newest epesi version, and the sealed
     * diagnostics when the administrator opted in. Notifies the super
     * administrators about a new version once per version.
     */
    public function checkIn(): StoreSetting
    {
        $setting = $this->setting();

        if (! $setting->hasIdentity()) {
            return $setting;
        }

        $sealed = $setting->isRegistered() && $setting->diagnostics
            ? rescue(fn () => $this->diagnostics->seal($this->diagnostics->collect()), null)
            : null;

        $this->guard(fn () => $this->apply($this->client->checkIn($sealed)));
        $this->notifyAboutNewVersion();

        return $setting->refresh();
    }

    /** Licence moved with the installation: asks the registered e-mail to confirm. */
    public function requestTransfer(): void
    {
        $this->apply($this->client->requestTransfer());
    }

    /** "Delete my registration": the store forgets the personal data, this installation its identity. */
    public function delete(): void
    {
        $this->client->deleteRegistration();
        $this->forget();
    }

    /**
     * Stores what the store answered.
     *
     * @param  array<string, mixed>  $answer
     */
    public function apply(array $answer): void
    {
        $status = match ($answer['status'] ?? null) {
            'confirmed' => StoreSetting::REGISTERED,
            'new', 'pending' => StoreSetting::PENDING,
            'revoked' => StoreSetting::REVOKED,
            default => StoreSetting::UNREGISTERED,
        };

        $setting = $this->setting();
        $core = $answer['core'] ?? null;

        $setting->update([
            'registration_status' => $status,
            'licence_key' => $status === StoreSetting::REGISTERED ? ($answer['licence_key'] ?? $setting->licence_key) : null,
            'registered_email' => $answer['email'] ?? null,
            'registered_url' => $answer['registered_url'] ?? null,
            'registered_at' => $status === StoreSetting::REGISTERED ? ($setting->registered_at ?? now()) : null,
            'url_mismatch' => ! ($answer['url_matches'] ?? true),
            'transfer_pending' => (bool) ($answer['transfer_pending'] ?? false),
            'diagnostics' => (bool) ($answer['diagnostics'] ?? false),
            'latest_core_version' => is_array($core) ? ($core['version'] ?? null) : $setting->latest_core_version,
            'latest_core_security' => is_array($core) ? (bool) ($core['security'] ?? false) : $setting->latest_core_security,
            'last_check_at' => now(),
        ]);
    }

    /** A newer epesi than this one, from the last check-in. */
    public function newerVersion(): ?string
    {
        $latest = $this->setting()->latest_core_version;

        return filled($latest) && version_compare($latest, Version::current(), '>') ? $latest : null;
    }

    /**
     * Tells every active super administrator, once per version, through the
     * bell. Sent now rather than queued (Reminders/Watchdog do the same).
     */
    public function notifyAboutNewVersion(): void
    {
        $setting = $this->setting();
        $version = $this->newerVersion();

        if ($version === null || $setting->notified_version === $version) {
            return;
        }

        $security = $setting->latest_core_security;

        User::role('super_admin')->where('active', true)->get()->each(function (User $user) use ($version, $security): void {
            $notification = Notification::make()
                ->title($security ? __('Security update available') : __('New epesi version is available'))
                ->body(__('epesi :version is available; this installation runs :current.', ['version' => $version, 'current' => Version::current()]))
                ->icon($security ? 'heroicon-o-shield-exclamation' : 'heroicon-o-arrow-up-circle')
                ->iconColor($security ? 'danger' : 'success')
                ->actions([
                    Action::make('open')
                        ->label(__('Epesi Store'))
                        ->url(Store::getUrl(panel: 'administration'))
                        ->markAsRead(),
                ]);

            $user->notifyNow($notification->toDatabase());
        });

        $setting->update(['notified_version' => $version]);
    }

    /**
     * What epesi already knows about its administrator and company, so the
     * store's form opens filled in.
     *
     * @return array<string, string>
     */
    public function prefill(?User $user): array
    {
        $contact = rescue(fn () => $user?->contact, null, report: false);
        $company = rescue(fn () => $contact?->company, null, report: false);
        $address = rescue(fn () => $company?->collection('addresses')->first() ?? $contact?->collection('addresses')->first(), null, report: false);
        $phone = rescue(fn () => $company?->collection('phones')->value('value'), null, report: false);
        $name = trim((string) $user?->name);

        return array_filter([
            'email' => $contact?->primaryEmail() ?? $user?->email,
            'first_name' => $contact?->first_name ?? (str_contains($name, ' ') ? Str::beforeLast($name, ' ') : $name),
            'last_name' => $contact?->last_name ?? (str_contains($name, ' ') ? Str::afterLast($name, ' ') : null),
            'company_name' => $company?->company_name,
            'address_1' => $address?->address_1,
            'address_2' => $address?->address_2,
            'postal_code' => $address?->postal_code,
            'city' => $address?->city,
            'country' => $address?->country,
            'phone' => $phone,
        ], fn ($value): bool => is_string($value) && $value !== '');
    }

    protected function forget(): void
    {
        $this->setting()->update([
            'instance_uuid' => null,
            'instance_secret' => null,
            'registration_status' => StoreSetting::UNREGISTERED,
            'licence_key' => null,
            'registered_email' => null,
            'registered_url' => null,
            'registered_at' => null,
            'url_mismatch' => false,
            'transfer_pending' => false,
            'diagnostics' => false,
        ]);
    }

    /**
     * The store's refusals change the local state rather than fail: an unknown
     * installation (its unconfirmed registration was purged) starts over, a
     * revoked one shows as revoked, a moved one shows the transfer offer.
     */
    protected function guard(callable $call): void
    {
        try {
            $call();
        } catch (StoreApiException $exception) {
            match ($exception->error) {
                StoreApiException::REGISTRATION_REQUIRED => $this->forget(),
                StoreApiException::REVOKED => $this->setting()->update(['registration_status' => StoreSetting::REVOKED, 'licence_key' => null]),
                StoreApiException::URL_MISMATCH => $this->setting()->update([
                    'url_mismatch' => true,
                    'registered_url' => $exception->payload['registered_url'] ?? null,
                ]),
                default => throw $exception,
            };
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
