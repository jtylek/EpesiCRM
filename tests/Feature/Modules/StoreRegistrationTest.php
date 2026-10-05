<?php

namespace Tests\Feature\Modules;

use App\Filament\Administration\Pages\About;
use App\Support\Version;
use Epesi\Modules\Store\Filament\Pages\Store;
use Epesi\Modules\Store\Models\StoreSetting;
use Epesi\Modules\Store\Services\Diagnostics;
use Epesi\Modules\Store\Services\Registration;
use Epesi\Modules\Store\Services\StoreClient;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The installation's side of registering with the Epesi Store: the About and
 * Store pages, the handshake, picking the licence key up, the daily check-in
 * and its new-version notice. The store itself is faked.
 */
class StoreRegistrationTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    /** StoreClient::API_URL — a literal: module classes load only once the app boots. */
    private const API = 'https://store.epe.si/manage/store-api';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->userWithRole('super_admin', ['name' => 'Ada Lovelace', 'email' => 'ada@example.com']));
        Filament::setCurrentPanel('administration');
    }

    public function test_about_shows_an_unregistered_installation_and_a_register_link(): void
    {
        Livewire::test(About::class)
            ->assertSee('epesi')
            ->assertSee('Version '.Version::current())
            ->assertSee('You are running unregistered version.')
            ->assertSee('Register your Epesi');
    }

    public function test_register_makes_an_identity_does_the_handshake_and_opens_the_form(): void
    {
        Http::fake([self::API.'/instances' => Http::response($this->answer('new') + ['register_url' => 'https://store.epe.si/manage/store/register/x?signature=y'])]);

        Livewire::test(About::class)
            ->callAction(TestAction::make('register')->schemaComponent('registration', 'content'))
            ->assertRedirect('https://store.epe.si/manage/store/register/x?signature=y');

        $setting = StoreSetting::current();
        $this->assertTrue($setting->hasIdentity());
        $this->assertTrue($setting->isPending());

        Http::assertSent(function (Request $request) use ($setting): bool {
            return $request->url() === self::API.'/instances'
                && $request['uuid'] === $setting->instance_uuid
                && $request['secret'] === $setting->instance_secret
                && $request['url'] === StoreClient::installationUrl()
                && $request['prefill']['email'] === 'ada@example.com'
                && $request['prefill']['first_name'] === 'Ada'
                && $request['prefill']['last_name'] === 'Lovelace';
        });
    }

    public function test_a_pending_store_page_picks_up_the_licence_key_once_confirmed(): void
    {
        $this->identity(StoreSetting::PENDING);

        Http::fake([
            self::API.'/instance' => Http::response($this->answer('confirmed')),
            self::API.'/catalog' => Http::response(['modules' => [], 'core' => null]),
        ]);

        Livewire::test(Store::class)->assertOk()->assertDontSee('Registration pending');

        $setting = StoreSetting::current();
        $this->assertTrue($setting->isRegistered());
        $this->assertSame('ABCDE-FGHIJ-KLMNO-PQRST', $setting->licence_key);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Epesi-Instance', $setting->instance_uuid)
            && $request->hasHeader('X-Epesi-Url', StoreClient::installationUrl()));

        Livewire::test(About::class)->assertSee('You are running registered version.');
    }

    public function test_store_page_offers_registration_instead_of_the_catalog(): void
    {
        Http::fake();

        Livewire::test(Store::class)->assertSee('Register Epesi')->assertSee('Automatic updates');

        Http::assertNothingSent();
    }

    public function test_pending_store_page_says_so(): void
    {
        $this->identity(StoreSetting::PENDING);
        Http::fake([self::API.'/instance' => Http::response($this->answer('pending'))]);

        Livewire::test(Store::class)->assertSee('Registration pending')->assertSee('ada@example.com');
    }

    public function test_settings_show_the_licence_key_read_only(): void
    {
        $this->identity(StoreSetting::REGISTERED);
        Http::fake([self::API.'/catalog' => Http::response(['modules' => [], 'core' => null])]);

        Livewire::test(Store::class)
            ->mountAction('settings')
            ->assertMountedActionModalSee('ABCDE-FGHIJ-KLMNO-PQRST')
            ->assertMountedActionModalDontSee('Optional. Required only for paid modules.');
    }

    public function test_a_paid_module_the_installation_does_not_hold_has_a_buy_button(): void
    {
        $this->identity(StoreSetting::REGISTERED);

        $module = fn (string $id, bool $licensed, ?string $buy) => [
            'id' => $id, 'name' => $id, 'version' => '1.0.0', 'epesi_core' => '*', 'sha256' => 'x',
            'price' => ['type' => 'paid', 'amount' => 4900, 'currency' => 'USD'],
            'licensed' => $licensed, 'download_url' => $licensed ? 'https://store.epe.si/manage/store-api/download/1' : null, 'buy_url' => $buy,
        ];

        Http::fake([self::API.'/catalog' => Http::response(['core' => null, 'modules' => [
            $module('acme/extra', false, 'https://store.epe.si/manage/store/buy/1?signature=s'),
            $module('acme/owned', true, null),
            $module('acme/old-store', false, null),
        ]])]);

        Livewire::test(Store::class)
            ->assertTableActionVisible('buy', 'acme/extra')
            ->assertTableActionHidden('buy', 'acme/owned')
            ->assertTableActionHidden('buy', 'acme/old-store');
    }

    public function test_check_in_seals_diagnostics_and_notifies_super_admins_once_per_version(): void
    {
        $this->identity(StoreSetting::REGISTERED, ['diagnostics' => true]);

        Http::fake([self::API.'/instance/check-in' => Http::response($this->answer('confirmed', ['core' => ['version' => '9.0.0', 'security' => false]]))]);

        app(Registration::class)->checkIn();
        app(Registration::class)->checkIn();

        $this->assertSame(1, auth()->user()->notifications()->count());
        $this->assertSame('New epesi version is available', auth()->user()->notifications()->first()->data['title']);
        $this->assertSame('9.0.0', StoreSetting::current()->notified_version);

        Http::assertSent(function (Request $request): bool {
            $sealed = (string) $request['diagnostics'];

            // Sealed: nothing readable on the wire.
            return $sealed !== '' && ! str_contains(base64_decode($sealed), PHP_VERSION);
        });
    }

    public function test_a_security_release_raises_a_security_notice(): void
    {
        $this->identity(StoreSetting::REGISTERED);
        Http::fake([
            self::API.'/instance/check-in' => Http::response($this->answer('confirmed', ['core' => ['version' => '9.0.0', 'security' => true]])),
            self::API.'/catalog' => Http::response(['modules' => [], 'core' => null]),
        ]);

        app(Registration::class)->checkIn();

        $this->assertSame('Security update available', auth()->user()->notifications()->first()->data['title']);
        Livewire::test(Store::class)->assertSee('Security update available');
    }

    public function test_an_unknown_installation_starts_over(): void
    {
        $this->identity(StoreSetting::PENDING);
        Http::fake([self::API.'/instance' => Http::response(['error' => 'registration_required', 'message' => 'x'], 401)]);

        app(Registration::class)->refreshStatus();

        $setting = StoreSetting::current();
        $this->assertFalse($setting->hasIdentity());
        $this->assertSame(StoreSetting::UNREGISTERED, $setting->registration_status);
    }

    public function test_a_moved_installation_offers_the_transfer(): void
    {
        $this->identity(StoreSetting::REGISTERED, ['url_mismatch' => true, 'registered_url' => 'https://old.example.com']);
        Http::fake([self::API.'/instance/transfer' => Http::response($this->answer('confirmed', ['url_matches' => false, 'transfer_pending' => true]))]);

        Livewire::test(Store::class)
            ->assertSee('This installation has moved')
            ->callAction('transfer');

        $this->assertTrue(StoreSetting::current()->transfer_pending);
    }

    public function test_deleting_the_registration_forgets_the_identity(): void
    {
        $this->identity(StoreSetting::REGISTERED);
        Http::fake([
            self::API.'/instance' => Http::response(['status' => 'deleted']),
            self::API.'/catalog' => Http::response(['modules' => [], 'core' => null]),
        ]);

        Livewire::test(Store::class)->callAction('deleteRegistration');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE');
        $setting = StoreSetting::current();
        $this->assertFalse($setting->hasIdentity());
        $this->assertNull($setting->licence_key);
    }

    public function test_diagnostics_hold_the_promised_fields_and_open_with_the_private_key(): void
    {
        $data = app(Diagnostics::class)->collect();

        foreach (['epesi', 'php','database', 'os', 'web_server', 'locale', 'timezone', 'installed', 'cron_last_run', 'users', 'contacts', 'companies', 'modules'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }

        $private = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $public = openssl_pkey_get_details($private)['key'];

        $envelope = json_decode(base64_decode(app(Diagnostics::class)->seal(['users' => 3], $public)), true);
        openssl_private_decrypt(base64_decode($envelope['k']), $key, $private, OPENSSL_PKCS1_OAEP_PADDING);
        $json = openssl_decrypt(base64_decode($envelope['d']), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, base64_decode($envelope['iv']), base64_decode($envelope['tag']));

        $this->assertSame(['users' => 3], json_decode($json, true));
        $this->assertNotFalse(openssl_pkey_get_public(StoreClient::SEAL_PUBLIC_KEY));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function identity(string $status, array $extra = []): void
    {
        StoreSetting::current()->update([
            'instance_uuid' => '11111111-2222-4333-8444-555555555555',
            'instance_secret' => str_repeat('s', 64),
            'registration_status' => $status,
            'registered_email' => 'ada@example.com',
            'licence_key' => $status === StoreSetting::REGISTERED ? 'ABCDE-FGHIJ-KLMNO-PQRST' : null,
            ...$extra,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function answer(string $status, array $extra = []): array
    {
        return [
            'status' => $status,
            'email' => 'ada@example.com',
            'licence_key' => $status === 'confirmed' ? 'ABCDE-FGHIJ-KLMNO-PQRST' : null,
            'registered_url' => $status === 'confirmed' ? StoreClient::installationUrl() : null,
            'url_matches' => true,
            'transfer_pending' => false,
            'diagnostics' => true,
            'core' => null,
            ...$extra,
        ];
    }
}
