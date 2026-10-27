<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\OAuth\Enums\TokenStatus;
use Epesi\Modules\OAuth\Events\TokenConnected;
use Epesi\Modules\OAuth\Events\TokenNeedsReconnect;
use Epesi\Modules\OAuth\Exceptions\OAuthException;
use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\OAuthAppResource;
use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Pages\CreateOAuthApp;
use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Pages\EditOAuthApp;
use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Pages\ListOAuthApps;
use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Pages\ViewOAuthApp;
use Epesi\Modules\OAuth\Models\OAuthClient;
use Epesi\Modules\OAuth\Models\OAuthToken;
use Epesi\Modules\OAuth\OAuthProviders;
use Epesi\Modules\OAuth\Providers\AppCheck;
use Epesi\Modules\OAuth\Providers\GoogleProvider;
use Epesi\Modules\OAuth\Providers\MicrosoftProvider;
use Epesi\Modules\OAuth\Services\OAuthFlow;
use Epesi\Modules\OAuth\Services\OAuthTokens;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Livewire\Notifications;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\Fakes\FakeChannel;
use Tests\Fakes\FakeOAuthProvider;
use Tests\TestCase;

/**
 * The shared OAuth layer: the provider registry, the connect flow (state and
 * PKCE), the token service's locked refresh, the cron renewal and
 * Administration → OAuth Apps. A mail account is the owner throughout.
 */
class OAuthTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        FakeChannel::setUpTable();

        $this->user = $this->userWithRole('employee', ['email' => 'eli@ourcompany.test']);
        $this->actingAs($this->user);

        OAuthClient::create(['provider' => 'microsoft', 'client_id' => 'ms-client', 'client_secret' => 'ms-secret']);
        OAuthClient::create(['provider' => 'google', 'client_id' => 'g-client', 'client_secret' => 'g-secret']);
    }

    protected function tearDown(): void
    {
        OAuthProviders::forget(FakeOAuthProvider::KEY);
        FakeOAuthProvider::$appTest = null;
        FakeOAuthProvider::$testedApps = [];

        parent::tearDown();
    }

    protected function account(array $attributes = []): MailAccount
    {
        return MailAccount::create([
            'user_id' => $this->user->id,
            'name' => 'Work',
            'email' => 'me@ourcompany.test',
            'auth_method' => 'microsoft',
            'imap_host' => 'outlook.office365.com',
            'smtp_host' => 'smtp.office365.com',
            ...$attributes,
        ]);
    }

    protected function connected(MailAccount $account, array $attributes = []): OAuthToken
    {
        return $account->oauthTokens()->create([
            'provider' => $account->oauthProvider(),
            'access_token' => 'old-access',
            'refresh_token' => 'old-refresh',
            'expires_at' => now()->subMinute(),
            ...$attributes,
        ]);
    }

    /** start() as a page's Connect button calls it; returns the state and the query sent to the provider. */
    protected function startFlow(MailAccount $account, string $provider = 'microsoft'): array
    {
        $url = app(OAuthFlow::class)->start($account, $provider, $this->user, '/user-settings/mail-accounts');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return [$url, $query];
    }

    protected function idToken(array $claims): string
    {
        $encode = fn (array $part): string => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');

        return $encode(['alg' => 'none']).'.'.$encode($claims).'.signature';
    }

    public function test_the_registry_holds_microsoft_and_google_and_takes_a_modules_provider(): void
    {
        $this->assertInstanceOf(MicrosoftProvider::class, OAuthProviders::get('microsoft'));
        $this->assertInstanceOf(GoogleProvider::class, OAuthProviders::get('google'));
        $this->assertNull(OAuthProviders::find('fake'));

        OAuthProviders::register(FakeOAuthProvider::class);

        $this->assertInstanceOf(FakeOAuthProvider::class, OAuthProviders::get('fake'));
        // Sorted by label; a Premium module in the checkout (Allegro) may add its own.
        $this->assertSame(['fake', 'google', 'microsoft'], array_values(array_intersect(array_keys(OAuthProviders::all()), ['fake', 'google', 'microsoft'])));

        $this->expectException(OAuthException::class);
        OAuthProviders::get('nope');
    }

    public function test_microsoft_puts_the_tenant_into_its_urls_and_never_anything_else(): void
    {
        $provider = OAuthProviders::get('microsoft');

        $this->assertSame('https://login.microsoftonline.com/common/oauth2/v2.0/token', $provider->tokenUrl(new OAuthClient));
        $this->assertSame('https://login.microsoftonline.com/contoso.onmicrosoft.com/oauth2/v2.0/authorize', $provider->authorizeUrl(new OAuthClient(['tenant' => 'contoso.onmicrosoft.com'])));
        $this->assertSame('https://login.microsoftonline.com/common/oauth2/v2.0/token', $provider->tokenUrl(new OAuthClient(['tenant' => '../evil?x=1'])));
    }

    public function test_connect_sends_the_user_to_the_provider_with_state_and_an_s256_challenge(): void
    {
        $account = $this->account();

        [$url, $query] = $this->startFlow($account);

        $this->assertStringStartsWith('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?', $url);
        $this->assertSame('ms-client', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame(route('epesi.oauth.callback'), $query['redirect_uri']);
        $this->assertStringContainsString('https://outlook.office.com/IMAP.AccessAsUser.All', $query['scope']);
        $this->assertStringContainsString('offline_access', $query['scope']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame('me@ourcompany.test', $query['login_hint']);

        $flow = session(OAuthFlow::SESSION_KEY)[$query['state']];
        $this->assertSame(OAuthFlow::challenge($flow['verifier']), $query['code_challenge']);
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', $flow['verifier'], true)), '+/', '-_'), '='), $query['code_challenge']);
        $this->assertSame($account->id, $flow['owner_id']);
        $this->assertArrayNotHasKey('client_secret', $query);

        // Google: offline access, and consent again so a refresh token comes back.
        [, $google] = $this->startFlow($this->account(['name' => 'Gmail', 'auth_method' => 'google']), 'google');
        $this->assertSame('offline', $google['access_type']);
        $this->assertSame('consent', $google['prompt']);
    }

    public function test_connect_needs_the_app_set_up_and_an_owner_the_user_may_connect(): void
    {
        OAuthClient::query()->where('provider', 'google')->update(['client_secret' => null]);

        try {
            $this->startFlow($this->account(['auth_method' => 'google']), 'google');
            $this->fail('An app without a secret was used.');
        } catch (OAuthException $e) {
            $this->assertStringContainsString('Administration', $e->getMessage());
        }

        $theirs = MailAccount::create(['user_id' => $this->userWithRole('employee')->id, 'name' => 'Theirs', 'email' => 'them@ourcompany.test', 'auth_method' => 'microsoft']);

        $this->expectException(AuthorizationException::class);
        app(OAuthFlow::class)->start($theirs, 'microsoft', $this->user);
    }

    public function test_not_even_a_super_admin_connects_someone_elses_mailbox(): void
    {
        $admin = $this->userWithRole('super_admin');
        $this->assertFalse($this->account()->mayConnectOAuth($admin));

        $this->expectException(AuthorizationException::class);
        app(OAuthFlow::class)->start($this->account(['name' => 'Other']), 'microsoft', $admin);
    }

    public function test_the_callback_exchanges_the_code_with_the_verifier_and_stores_the_tokens_encrypted(): void
    {
        $account = $this->account();
        [, $query] = $this->startFlow($account);
        $verifier = session(OAuthFlow::SESSION_KEY)[$query['state']]['verifier'];

        Http::fake(['login.microsoftonline.com/*' => Http::response([
            'access_token' => 'new-access',
            'refresh_token' => 'new-refresh',
            'expires_in' => 3600,
            'scope' => 'https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send',
            'id_token' => $this->idToken(['email' => 'me@ourcompany.test']),
        ])]);

        $this->get(route('epesi.oauth.callback', ['state' => $query['state'], 'code' => 'the-code']))
            ->assertRedirect(url('/user-settings/mail-accounts'));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://login.microsoftonline.com/common/oauth2/v2.0/token'
            && $request['grant_type'] === 'authorization_code'
            && $request['code'] === 'the-code'
            && $request['code_verifier'] === $verifier
            && $request['client_secret'] === 'ms-secret'
            && $request['redirect_uri'] === route('epesi.oauth.callback'));

        $token = $account->oauthToken('microsoft');
        $this->assertSame('new-access', $token->access_token);
        $this->assertSame('new-refresh', $token->refresh_token);
        $this->assertSame('me@ourcompany.test', $token->account);
        $this->assertSame(TokenStatus::Active, $token->status);
        $this->assertSame($this->user->id, $token->connected_by);
        $this->assertEqualsWithDelta(now()->addHour()->getTimestamp(), $token->expires_at->getTimestamp(), 5);

        // At rest: neither token in the clear, and the secret neither.
        $row = DB::table('epesi_oauth_tokens')->where('id', $token->id)->first();
        $this->assertStringNotContainsString('new-access', (string) $row->access_token);
        $this->assertStringNotContainsString('new-refresh', (string) $row->refresh_token);
        $this->assertStringNotContainsString('ms-secret', (string) DB::table('epesi_oauth_clients')->where('provider', 'microsoft')->value('client_secret'));
        $this->assertArrayNotHasKey('access_token', $token->toArray());

        // The state is used up.
        $this->get(route('epesi.oauth.callback', ['state' => $query['state'], 'code' => 'the-code']))->assertForbidden();
    }

    public function test_a_state_this_session_did_not_start_is_refused_without_calling_the_provider(): void
    {
        Http::fake();
        $account = $this->account();
        $this->startFlow($account);

        $this->get(route('epesi.oauth.callback', ['state' => 'forged', 'code' => 'x']))->assertForbidden();
        $this->get(route('epesi.oauth.callback', ['code' => 'x']))->assertForbidden();

        Http::assertNothingSent();
        $this->assertNull($account->oauthToken());
    }

    public function test_an_expired_state_is_refused(): void
    {
        Http::fake();
        [, $query] = $this->startFlow($this->account());

        $this->travel(config('epesi-oauth.flow_lifetime') + 1)->minutes();

        $this->get(route('epesi.oauth.callback', ['state' => $query['state'], 'code' => 'x']))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_the_callback_needs_the_user_who_started_it(): void
    {
        Http::fake();
        [, $query] = $this->startFlow($this->account());

        $this->actingAs($this->userWithRole('employee'))
            ->get(route('epesi.oauth.callback', ['state' => $query['state'], 'code' => 'x']))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_the_callback_needs_a_signed_in_user_and_is_off_in_demo_mode(): void
    {
        auth()->logout();
        $this->get(route('epesi.oauth.callback', ['state' => 'x']))->assertForbidden();

        config(['demo.enabled' => true]);
        $this->actingAs($this->user)->get(route('epesi.oauth.callback', ['state' => 'x']))->assertNotFound();
    }

    public function test_a_declined_consent_stores_nothing_and_says_so(): void
    {
        Http::fake();
        $account = $this->account();
        [, $query] = $this->startFlow($account);

        $this->get(route('epesi.oauth.callback', ['state' => $query['state'], 'error' => 'access_denied', 'error_description' => 'The user declined.']))
            ->assertRedirect(url('/user-settings/mail-accounts'));

        Http::assertNothingSent();
        $this->assertNull($account->oauthToken());
    }

    public function test_a_failed_code_exchange_stores_nothing(): void
    {
        $account = $this->account();
        [, $query] = $this->startFlow($account);
        Http::fake(['*' => Http::response(['error' => 'invalid_grant', 'error_description' => 'AADSTS70008: expired code'], 400)]);

        $this->get(route('epesi.oauth.callback', ['state' => $query['state'], 'code' => 'x']))
            ->assertRedirect(url('/user-settings/mail-accounts'));

        $this->assertNull($account->oauthToken());
    }

    public function test_an_expiring_token_is_refreshed_and_the_rotated_refresh_token_kept(): void
    {
        $account = $this->account();
        $this->connected($account);
        Http::fake(['*' => Http::response(['access_token' => 'fresh', 'refresh_token' => 'rotated', 'expires_in' => 3600])]);

        $this->assertSame('fresh', app(OAuthTokens::class)->accessToken($account, 'microsoft'));

        Http::assertSent(fn (Request $request): bool => $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'old-refresh');
        $this->assertSame('rotated', $account->oauthToken()->refresh_token);

        // Valid now: no second request.
        $this->assertSame('fresh', app(OAuthTokens::class)->accessToken($account));
        Http::assertSentCount(1);
    }

    public function test_a_refresh_without_a_new_refresh_token_keeps_the_old_one(): void
    {
        $account = $this->account();
        $this->connected($account);
        Http::fake(['*' => Http::response(['access_token' => 'fresh', 'expires_in' => 3600])]);

        app(OAuthTokens::class)->accessToken($account);

        $this->assertSame('old-refresh', $account->oauthToken()->refresh_token);
    }

    public function test_a_refresh_that_waited_for_the_lock_uses_the_token_the_other_process_stored(): void
    {
        $account = $this->account();
        $stale = $this->connected($account);
        Http::fake();

        // Another process refreshed (and rotated) it while this one waited.
        OAuthToken::query()->whereKey($stale->id)->first()->forceFill([
            'access_token' => 'theirs',
            'refresh_token' => 'their-rotated',
            'expires_at' => now()->addHour(),
        ])->save();

        $token = app(OAuthTokens::class)->refresh($stale, 300);

        Http::assertNothingSent();
        $this->assertSame('theirs', $token->access_token);
        $this->assertSame('their-rotated', $token->refresh_token);
    }

    public function test_a_refresh_does_not_run_while_another_holds_the_lock(): void
    {
        config(['epesi-oauth.lock_wait' => 0]);
        $account = $this->account();
        $token = $this->connected($account);
        Http::fake();

        $lock = Cache::lock('epesi-oauth-refresh:'.$token->id, 60);
        $this->assertTrue($lock->get());

        try {
            app(OAuthTokens::class)->accessToken($account);
            $this->fail('Refreshed although another process held the lock.');
        } catch (OAuthException $e) {
            $this->assertStringContainsString('another process', $e->getMessage());
        } finally {
            $lock->release();
        }

        Http::assertNothingSent();
        $this->assertSame('old-refresh', $token->fresh()->refresh_token);
    }

    public function test_invalid_grant_marks_the_token_for_reconnecting_and_tells_the_owner_once(): void
    {
        $account = $this->account();
        $this->connected($account);
        Http::fake(['*' => Http::response(['error' => 'invalid_grant', 'error_description' => 'AADSTS50173: revoked'], 400)]);

        foreach ([1, 2] as $attempt) {
            try {
                app(OAuthTokens::class)->accessToken($account);
                $this->fail('A refused token was handed out.');
            } catch (OAuthException $e) {
                $this->assertStringContainsString('Connect the account again', $e->getMessage());
            }
        }

        $token = $account->oauthToken();
        $this->assertSame(TokenStatus::NeedsReconnect, $token->status);
        $this->assertStringContainsString('invalid_grant', $token->last_error);
        $this->assertStringNotContainsString('old-refresh', $token->last_error);

        // The second attempt asked nobody: it is known to need reconnecting.
        Http::assertSentCount(1);
        $this->assertSame(1, $this->user->notifications()->count());
    }

    public function test_other_refresh_failures_keep_the_token_active(): void
    {
        $account = $this->account();
        $this->connected($account);
        Http::fake(['*' => Http::response(['error' => 'temporarily_unavailable'], 503)]);

        try {
            app(OAuthTokens::class)->accessToken($account);
            $this->fail('No token can have been handed out.');
        } catch (OAuthException) {
        }

        $this->assertSame(TokenStatus::Active, $account->oauthToken()->status);
        $this->assertStringContainsString('temporarily_unavailable', $account->oauthToken()->last_error);
    }

    public function test_an_account_that_was_never_connected_says_so(): void
    {
        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage('Not connected');

        app(OAuthTokens::class)->accessToken($this->account(), 'microsoft');
    }

    public function test_cron_renews_tokens_about_to_expire_and_only_those(): void
    {
        $this->assertTrue(collect(app(Schedule::class)->events())->contains(fn ($event): bool => str_contains((string) $event->command, 'oauth:refresh')));

        $due = $this->connected($this->account(), ['expires_at' => now()->addMinutes(5)]);
        $later = $this->connected($this->account(['name' => 'B']), ['expires_at' => now()->addHours(2)]);
        $refused = $this->connected($this->account(['name' => 'C']), ['status' => TokenStatus::NeedsReconnect]);
        Http::fake(['*' => Http::response(['access_token' => 'fresh', 'refresh_token' => 'rotated', 'expires_in' => 3600])]);

        $this->artisan('oauth:refresh')->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertSame('fresh', $due->fresh()->access_token);
        $this->assertSame('old-access', $later->fresh()->access_token);
        $this->assertSame('old-access', $refused->fresh()->access_token);
    }

    public function test_disconnecting_revokes_at_google_and_forgets_the_token(): void
    {
        $account = $this->account(['auth_method' => 'google']);
        $this->connected($account, ['expires_at' => now()->addHour()]);
        Http::fake(['oauth2.googleapis.com/revoke' => Http::response()]);

        app(OAuthTokens::class)->disconnect($account, 'google');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/revoke' && $request['token'] === 'old-refresh');
        $this->assertNull($account->oauthToken());
    }

    public function test_deleting_the_owner_deletes_its_tokens(): void
    {
        $account = $this->account();
        $this->connected($account);

        $account->delete();

        $this->assertSame(0, OAuthToken::query()->count());
    }

    public function test_a_providers_own_callback_path_environment_basic_auth_and_headers(): void
    {
        OAuthProviders::register(FakeOAuthProvider::class);
        $provider = OAuthProviders::get('fake');
        OAuthClient::create(['provider' => 'fake', 'environment' => 'production', 'client_id' => 'p-client', 'client_secret' => 'p-secret']);
        OAuthClient::create(['provider' => 'fake', 'environment' => 'sandbox', 'client_id' => 's-client', 'client_secret' => 's-secret']);
        $channel = FakeChannel::create(['name' => 'Shop']);
        $this->actingAs($admin = $this->userWithRole('super_admin'));

        $this->assertSame(url('administration/fake/callback'), OAuthProviders::redirectUri($provider));

        $url = app(OAuthFlow::class)->start($channel, 'fake', $admin, '/administration', ['orders:read', 'offers:write'], 'sandbox');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertStringStartsWith('https://sandbox.fake.test/auth/authorize?', $url);
        $this->assertSame('s-client', $query['client_id']);
        $this->assertSame('orders:read offers:write', $query['scope']);
        $this->assertSame(url('administration/fake/callback'), $query['redirect_uri']);

        // Read at run time: a module's setting changed after registration.
        FakeOAuthProvider::$userAgent = 'Shop-Sync/2.0';
        Http::fake(['sandbox.fake.test/*' => Http::response(['access_token' => 'fake-access', 'refresh_token' => 'fake-refresh', 'expires_in' => 60])]);

        $this->get('/administration/fake/callback?'.http_build_query(['state' => $query['state'], 'code' => 'c']))
            ->assertRedirect(url('/administration'));

        $token = $channel->oauthToken('fake');
        $this->assertSame('fake-access', $token->access_token);
        $this->assertSame('sandbox', $token->environment);
        $this->assertSame('sandbox', app(OAuthTokens::class)->environment($channel, 'fake'));
        $this->assertSame(['orders:read', 'offers:write'], $token->scopes);

        // 60 seconds is within refresh_ahead: handed out only after a refresh.
        app(OAuthTokens::class)->accessToken($channel, 'fake');

        $sent = Http::recorded()->map(fn (array $pair): Request => $pair[0]);
        $this->assertSame(['authorization_code', 'refresh_token'], $sent->map(fn (Request $request) => $request['grant_type'])->all());
        $this->assertTrue($sent->every(fn (Request $request): bool => $request->header('User-Agent') === ['Shop-Sync/2.0']
            && $request->url() === 'https://sandbox.fake.test/auth/token'
            && $request->header('Authorization') === ['Basic '.base64_encode('s-client:s-secret')]
            && ! isset($request['client_secret'])));
        $this->assertSame(url('administration/fake/callback'), $sent->first()['redirect_uri']);

        // The same headers for a module's own API calls.
        Http::fake(['api.fake.test/*' => Http::response(['ok' => true])]);
        app(OAuthTokens::class)->authorizedHttp($channel, 'fake')->get('https://api.fake.test/orders');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.fake.test/orders'
            && $request->header('User-Agent') === ['Shop-Sync/2.0']
            && $request->hasHeader('Authorization', 'Bearer '.$channel->oauthToken('fake')->access_token));

        FakeOAuthProvider::$userAgent = 'epesi-test/1.0 (+https://epe.si)';
    }

    public function test_a_providers_own_callback_path_refuses_another_providers_flow(): void
    {
        OAuthProviders::register(FakeOAuthProvider::class);
        Http::fake();
        [, $query] = $this->startFlow($this->account());

        $this->get('/administration/fake/callback?'.http_build_query(['state' => $query['state'], 'code' => 'c']))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_an_admin_connects_a_business_record_and_two_records_hold_two_tokens(): void
    {
        $this->actingAs($admin = $this->userWithRole('super_admin'));
        $north = FakeChannel::create(['name' => 'North']);
        $south = FakeChannel::create(['name' => 'South']);
        Event::fake([TokenConnected::class]);

        // The owner's own rule: an employee may not connect it.
        try {
            app(OAuthFlow::class)->start($north, 'google', $this->user);
            $this->fail('An employee connected a channel.');
        } catch (AuthorizationException) {
        }

        Http::fake(fn (Request $request) => Http::response(['access_token' => $request['code'].'-access', 'refresh_token' => $request['code'].'-refresh', 'expires_in' => 3600]));

        foreach (['n' => $north, 's' => $south] as $name => $channel) {
            $url = app(OAuthFlow::class)->start($channel, 'google', $admin, '/administration');
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $this->assertArrayNotHasKey('login_hint', $query);

            $this->get(route('epesi.oauth.callback', ['state' => $query['state'], 'code' => $name]))->assertRedirect(url('/administration'));
        }

        $this->assertSame('n-access', app(OAuthTokens::class)->accessToken($north, 'google'));
        $this->assertSame('s-access', app(OAuthTokens::class)->accessToken($south, 'google'));
        $this->assertSame(2, OAuthToken::query()->where('provider', 'google')->count());
        $this->assertSame('test_oauth_channel', $north->oauthToken()->owner_type);

        Event::assertDispatched(TokenConnected::class, fn (TokenConnected $event): bool => $event->owner->is($north) && $event->user->is($admin));
        Event::assertDispatchedTimes(TokenConnected::class, 2);
    }

    public function test_connecting_again_with_more_scopes_updates_the_same_token(): void
    {
        $account = $this->account(['auth_method' => 'google']);
        $first = $this->connected($account, ['scopes' => ['https://mail.google.com/']]);

        $url = app(OAuthFlow::class)->start($account, 'google', $this->user, null, ['https://mail.google.com/', 'https://www.googleapis.com/auth/calendar']);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        Http::fake(['*' => Http::response(['access_token' => 'wider', 'refresh_token' => 'wider-refresh', 'expires_in' => 3600, 'scope' => 'https://mail.google.com/ https://www.googleapis.com/auth/calendar'])]);

        $this->get(route('epesi.oauth.callback', ['state' => $query['state'], 'code' => 'c']));

        $this->assertSame(1, $account->oauthTokens()->count());
        $token = $account->oauthToken();
        $this->assertSame($first->id, $token->id);
        $this->assertSame(['https://mail.google.com/', 'https://www.googleapis.com/auth/calendar'], $token->scopes);
        $this->assertSame('wider-refresh', $token->refresh_token);
    }

    public function test_a_refused_business_record_tells_the_super_admins_and_raises_the_event(): void
    {
        $admin = $this->userWithRole('super_admin');
        $channel = FakeChannel::create(['name' => 'North']);
        $channel->oauthTokens()->create(['provider' => 'google', 'access_token' => 'a', 'refresh_token' => 'r', 'expires_at' => now()->subMinute()]);
        Event::fake([TokenNeedsReconnect::class]);
        Http::fake(['*' => Http::response(['error' => 'invalid_grant'], 400)]);

        try {
            app(OAuthTokens::class)->accessToken($channel, 'google');
        } catch (OAuthException) {
        }

        Event::assertDispatched(TokenNeedsReconnect::class, fn (TokenNeedsReconnect $event): bool => $event->owner->is($channel) && str_contains($event->error, 'invalid_grant'));
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(0, $this->user->notifications()->count());
    }

    public function test_a_forced_refresh_renews_once_even_when_two_workers_got_a_401(): void
    {
        $account = $this->account();
        $this->connected($account, ['access_token' => 'refused', 'expires_at' => now()->addHour()]);
        Http::fake(['*' => Http::response(['access_token' => 'renewed', 'refresh_token' => 'rotated', 'expires_in' => 3600])]);

        // Valid by its expiry, refused by the API: renewed anyway.
        $this->assertSame('renewed', app(OAuthTokens::class)->freshToken($account, 'microsoft', rejected: 'refused'));
        // A second worker that was refused the same old token gets the new one.
        $this->assertSame('renewed', app(OAuthTokens::class)->freshToken($account, 'microsoft', rejected: 'refused'));
        Http::assertSentCount(1);

        // Without naming the refused token it always renews.
        app(OAuthTokens::class)->freshToken($account);
        Http::assertSentCount(2);

        // invalidate(): the next accessToken() renews.
        app(OAuthTokens::class)->invalidate($account);
        app(OAuthTokens::class)->accessToken($account);
        Http::assertSentCount(3);
    }

    public function test_an_app_token_uses_client_credentials_and_is_cached(): void
    {
        OAuthProviders::register(FakeOAuthProvider::class);
        OAuthClient::create(['provider' => 'fake', 'client_id' => 'p-client', 'client_secret' => 'p-secret']);
        Http::fake(['fake.test/*' => Http::response(['access_token' => 'app-token', 'expires_in' => 43200])]);

        $this->assertSame('app-token', app(OAuthTokens::class)->appToken('fake'));
        $this->assertSame('app-token', app(OAuthTokens::class)->appToken('fake'));

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://fake.test/auth/token'
            && $request['grant_type'] === 'client_credentials'
            && $request->hasHeader('User-Agent'));

        // requestAppToken() asks every time and keeps nothing: what an app test uses.
        $app = OAuthClient::for('fake');
        $this->assertSame(['access_token' => 'app-token', 'expires_in' => 43200], app(OAuthTokens::class)->requestAppToken(OAuthProviders::get('fake'), $app));
        app(OAuthTokens::class)->requestAppToken(OAuthProviders::get('fake'), $app);
        Http::assertSentCount(3);
        $this->assertSame(0, OAuthToken::query()->count());

        // An app without a secret is refused before anything is sent.
        $app->forceFill(['client_secret' => null])->save();
        try {
            app(OAuthTokens::class)->requestAppToken(OAuthProviders::get('fake'), $app->fresh());
            $this->fail('An unconfigured app asked for a token.');
        } catch (OAuthException $e) {
            $this->assertStringContainsString('isn\'t set up yet', $e->getMessage());
        }
        Http::assertSentCount(3);
    }

    public function test_a_refusal_carries_the_providers_error_code_and_status(): void
    {
        $e = OAuthException::fromProvider('Fake Market', 'invalid_client', 'Bad credentials', 401);

        $this->assertSame('invalid_client', $e->providerError);
        $this->assertSame(401, $e->providerStatus);
        $this->assertStringContainsString('invalid_client: Bad credentials', $e->getMessage());
        $this->assertSame(0, OAuthException::notConfigured('Fake Market')->providerStatus);
    }

    /** The body of the notification titled $title, which must have been sent. */
    protected function notificationBody(string $title): string
    {
        $component = new Notifications;
        $component->mount();
        $notification = $component->notifications->first(fn ($notification): bool => $notification->getTitle() === $title);

        $this->assertNotNull($notification, "No notification \"{$title}\" was sent.");

        return (string) $notification->getBody();
    }

    public function test_test_app_is_offered_only_for_a_provider_that_can_test_an_app(): void
    {
        OAuthProviders::register(FakeOAuthProvider::class);
        $this->asSuperAdmin();

        // Microsoft and Google can't test an app without a sign-in: no button.
        foreach (['microsoft', 'google'] as $provider) {
            $app = OAuthClient::for($provider);
            Livewire::test(ViewOAuthApp::class, ['record' => $app->getKey()])->assertActionHidden('testOAuthApp');
            Livewire::test(EditOAuthApp::class, ['record' => $app->getKey()])->assertActionHidden('testOAuthApp');
        }

        $fake = OAuthClient::create(['provider' => 'fake', 'client_id' => 'p-client', 'client_secret' => 'p-secret']);
        Livewire::test(ViewOAuthApp::class, ['record' => $fake->getKey()])->assertActionHidden('testOAuthApp');

        FakeOAuthProvider::$appTest = [AppCheck::pass('Token', 'Issued.'), AppCheck::pass('Read call', 'Answered.')];

        Livewire::test(ViewOAuthApp::class, ['record' => $fake->getKey()])
            ->assertActionVisible('testOAuthApp')
            ->callAction('testOAuthApp');

        $body = $this->notificationBody('The app works');
        $this->assertStringContainsString('<strong>Token</strong> (passed): Issued.', $body);
        $this->assertStringContainsString('<strong>Read call</strong> (passed): Answered.', $body);
        $this->assertStringContainsString('doesn&#039;t check the redirect URI or the permissions', $body);
        $this->assertSame([$fake->getKey()], array_map(fn (OAuthClient $app) => $app->getKey(), FakeOAuthProvider::$testedApps));

        // Edit says first that the saved app is tested, and tests that one, not the form.
        FakeOAuthProvider::$testedApps = [];
        Livewire::test(EditOAuthApp::class, ['record' => $fake->getKey()])
            ->assertActionVisible('testOAuthApp')
            ->mountAction('testOAuthApp')
            ->assertMountedActionModalSee('Changes on this form that are not saved yet are not tested: save them first.');
        $this->assertSame([], FakeOAuthProvider::$testedApps, 'nothing runs before the confirmation');
        Livewire::test(EditOAuthApp::class, ['record' => $fake->getKey()])
            ->fillForm(['client_id' => 'unsaved-client'])
            ->callAction('testOAuthApp');
        $this->assertSame('p-client', FakeOAuthProvider::$testedApps[0]->client_id);
        $this->assertSame('p-client', $fake->fresh()->client_id, 'testing saves nothing');

        // A failed check makes the whole test fail.
        FakeOAuthProvider::$appTest = [AppCheck::pass('Token', 'Issued.'), AppCheck::fail('Read call', 'Refused (HTTP 403).')];
        Livewire::test(ViewOAuthApp::class, ['record' => $fake->getKey()])->callAction('testOAuthApp');
        $this->assertStringContainsString('<strong>Read call</strong> (failed): Refused (HTTP 403).', $this->notificationBody('App test failed'));

        // A refusal the provider throws is the result, not an error page.
        FakeOAuthProvider::$appTest = OAuthException::fromProvider('Fake Market', 'invalid_client', 'Bad credentials', 401);
        Livewire::test(ViewOAuthApp::class, ['record' => $fake->getKey()])->callAction('testOAuthApp');
        $this->assertStringContainsString('invalid_client: Bad credentials', $this->notificationBody('App test failed'));
    }

    public function test_test_app_says_when_the_app_has_no_client_id_or_secret_without_asking_the_provider(): void
    {
        OAuthProviders::register(FakeOAuthProvider::class);
        FakeOAuthProvider::$appTest = [AppCheck::pass('Token', 'Issued.')];
        $this->asSuperAdmin();
        $fake = OAuthClient::create(['provider' => 'fake', 'environment' => 'sandbox', 'client_id' => 's-client']);

        Livewire::test(ViewOAuthApp::class, ['record' => $fake->getKey()])->callAction('testOAuthApp');

        $this->assertStringContainsString('No client ID or secret is stored for this app.', $this->notificationBody('App test failed'));
        $this->assertSame([], FakeOAuthProvider::$testedApps);
    }

    public function test_test_app_is_off_in_demo_mode_and_for_anyone_but_a_super_admin(): void
    {
        OAuthProviders::register(FakeOAuthProvider::class);
        FakeOAuthProvider::$appTest = [AppCheck::pass('Token', 'Issued.')];
        $fake = OAuthClient::create(['provider' => 'fake', 'client_id' => 'p-client', 'client_secret' => 'p-secret']);

        Filament::setCurrentPanel('administration');
        $this->actingAs($this->userWithRole('manager'))->get(OAuthAppResource::getUrl('view', ['record' => $fake]))->assertForbidden();

        $this->asSuperAdmin();
        config(['demo.enabled' => true]);

        Livewire::test(ViewOAuthApp::class, ['record' => $fake->getKey()])
            ->callAction('testOAuthApp')
            ->assertNotified('Unavailable in demo mode');
        Livewire::test(EditOAuthApp::class, ['record' => $fake->getKey()])
            ->callAction('testOAuthApp')
            ->assertNotified('Unavailable in demo mode');

        $this->assertSame([], FakeOAuthProvider::$testedApps);
    }

    public function test_deleting_a_business_record_deletes_its_tokens(): void
    {
        $channel = FakeChannel::create(['name' => 'North']);
        $channel->oauthTokens()->create(['provider' => 'google', 'access_token' => 'a']);
        $other = FakeChannel::create(['name' => 'South']);
        $other->oauthTokens()->create(['provider' => 'google', 'access_token' => 'b']);

        $channel->delete();

        $this->assertSame(['b'], OAuthToken::query()->get()->pluck('access_token')->all());
    }

    protected function asSuperAdmin(): void
    {
        Filament::setCurrentPanel('administration');
        $this->actingAs($this->userWithRole('super_admin'));
    }

    public function test_only_a_super_admin_opens_oauth_apps(): void
    {
        Filament::setCurrentPanel('administration');
        $microsoft = OAuthClient::for('microsoft');
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->get(OAuthAppResource::getUrl())->assertForbidden();
        $this->actingAs($manager)->get(OAuthAppResource::getUrl('create'))->assertForbidden();
        $this->actingAs($manager)->get(OAuthAppResource::getUrl('edit', ['record' => $microsoft]))->assertForbidden();

        $this->actingAs($this->userWithRole('super_admin'));

        // The old settings page's address still opens the list.
        $this->assertSame(url('administration/oauth-apps'), OAuthAppResource::getUrl());
        $this->get(OAuthAppResource::getUrl())
            ->assertOk()
            ->assertSee('Microsoft 365')
            ->assertSee('g-client')
            ->assertDontSee('ms-secret')
            ->assertDontSee('g-secret');
        $this->get(OAuthAppResource::getUrl('view', ['record' => $microsoft]))
            ->assertOk()
            ->assertSee(route('epesi.oauth.callback'))
            ->assertSee('ms-client')
            ->assertDontSee('ms-secret');
        $this->get(OAuthAppResource::getUrl('edit', ['record' => $microsoft]))
            ->assertOk()
            ->assertSee(route('epesi.oauth.callback'))
            ->assertDontSee('ms-secret');
    }

    public function test_a_new_oauth_app_takes_a_provider_and_environment_not_yet_used(): void
    {
        OAuthProviders::register(FakeOAuthProvider::class);
        $this->asSuperAdmin();

        // Microsoft and Google have their one environment taken; the fake has two free.
        $offered = OAuthAppResource::providersWithFreeEnvironments();
        $this->assertSame('Fake Market', $offered['fake'] ?? null);
        $this->assertArrayNotHasKey('microsoft', $offered);
        $this->assertArrayNotHasKey('google', $offered);

        Livewire::test(CreateOAuthApp::class)
            ->fillForm(['provider' => 'fake'])
            ->assertFormSet(['environment' => null])
            ->assertSee(url('administration/fake/callback'))
            ->fillForm(['environment' => 'sandbox', 'client_id' => ' s-client ', 'client_secret' => 's-secret'])
            ->call('create')
            ->assertHasNoFormErrors();

        $sandbox = OAuthClient::for('fake', 'sandbox');
        $this->assertSame('s-client', $sandbox->client_id);
        $this->assertSame('s-secret', $sandbox->client_secret);
        $this->assertNull(OAuthClient::for('fake'));

        // Only the environment left is offered, and picked.
        $this->assertSame(['production' => 'Production'], OAuthAppResource::freeEnvironments('fake'));
        Livewire::test(CreateOAuthApp::class)
            ->fillForm(['provider' => 'fake'])
            ->assertFormSet(['environment' => 'production']);

        // A taken combination, sent anyway, is refused by the unique rule.
        Livewire::test(CreateOAuthApp::class)
            ->fillForm(['provider' => 'microsoft'])
            ->fillForm(['environment' => 'production', 'client_id' => 'other', 'client_secret' => 'other-secret'])
            ->call('create')
            ->assertHasFormErrors(['environment' => 'unique'])
            ->assertSee('This provider already has an app in this environment.');

        Livewire::test(CreateOAuthApp::class)
            ->fillForm(['provider' => 'fake'])
            ->fillForm(['environment' => 'production', 'client_id' => '', 'client_secret' => ''])
            ->call('create')
            ->assertHasFormErrors(['client_id' => 'required', 'client_secret' => 'required']);

        $this->assertSame('ms-client', OAuthClient::for('microsoft')->client_id);
        $this->assertSame(3, OAuthClient::query()->count());
    }

    public function test_editing_an_oauth_app_changes_only_it_and_an_empty_secret_keeps_the_stored_one(): void
    {
        $this->asSuperAdmin();
        $microsoft = OAuthClient::for('microsoft');

        Livewire::test(EditOAuthApp::class, ['record' => $microsoft->getKey()])
            ->assertFormSet(['client_id' => 'ms-client', 'client_secret' => null])
            ->assertDontSee('ms-secret')
            ->fillForm(['client_id' => 'ms-new', 'client_secret' => '', 'tenant' => 'contoso.onmicrosoft.com'])
            ->call('save')
            ->assertHasNoFormErrors();

        $microsoft->refresh();
        $this->assertSame('ms-new', $microsoft->client_id);
        $this->assertSame('ms-secret', $microsoft->client_secret);
        $this->assertSame('contoso.onmicrosoft.com', $microsoft->tenant);
        $this->assertSame('production', $microsoft->environment);

        $google = OAuthClient::for('google');
        $this->assertSame('g-client', $google->client_id);
        $this->assertSame('g-secret', $google->client_secret);

        Livewire::test(EditOAuthApp::class, ['record' => $microsoft->getKey()])
            ->fillForm(['client_secret' => 'ms-new-secret'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('ms-new-secret', $microsoft->refresh()->client_secret);

        Livewire::test(EditOAuthApp::class, ['record' => $microsoft->getKey()])
            ->fillForm(['tenant' => 'bad/tenant'])
            ->call('save')
            ->assertHasFormErrors(['tenant']);

        // Only Microsoft has a tenant.
        Livewire::test(EditOAuthApp::class, ['record' => $google->getKey()])
            ->assertFormFieldHidden('tenant');
    }

    public function test_a_providers_app_fields_are_entered_on_the_app_and_sent_from_the_environment_in_use(): void
    {
        OAuthProviders::register(FakeOAuthProvider::class);
        $this->asSuperAdmin();

        // Create: the provider's field appears with its default once the provider is picked.
        Livewire::test(CreateOAuthApp::class)
            ->assertFormFieldDoesNotExist('settings.user_agent')
            ->fillForm(['provider' => 'fake'])
            ->assertFormSet(['settings.user_agent' => FakeOAuthProvider::$userAgent])
            ->assertSee('Sent with every request.')
            ->fillForm(['environment' => 'sandbox', 'client_id' => 's-client', 'client_secret' => 's-secret', 'settings.user_agent' => ' Sandbox-Agent/1.0 '])
            ->call('create')
            ->assertHasNoFormErrors();

        $sandbox = OAuthClient::for('fake', 'sandbox');
        $this->assertSame(['user_agent' => 'Sandbox-Agent/1.0'], $sandbox->settings);
        $this->assertSame('Sandbox-Agent/1.0', $sandbox->setting('user_agent'));

        // Required when the provider says so.
        Livewire::test(CreateOAuthApp::class)
            ->fillForm(['provider' => 'fake'])
            ->fillForm(['environment' => 'production', 'client_id' => 'p-client', 'client_secret' => 'p-secret', 'settings.user_agent' => ''])
            ->call('create')
            ->assertHasFormErrors(['settings.user_agent' => 'required']);

        // Nothing stored: the field's default.
        $production = OAuthClient::create(['provider' => 'fake', 'client_id' => 'p-client', 'client_secret' => 'p-secret']);
        $this->assertSame(FakeOAuthProvider::$userAgent, $production->setting('user_agent'));
        $this->assertNull($production->setting('unknown'));

        // Each environment's requests carry that environment's app's value.
        Http::fake();
        $tokens = app(OAuthTokens::class);
        $tokens->http('fake', 'sandbox')->get('https://api.fake.test/a');
        $tokens->http('fake')->get('https://api.fake.test/b');
        $tokens->post(OAuthProviders::get('fake'), $sandbox, 'https://sandbox.fake.test/auth/token', ['grant_type' => 'client_credentials']);
        $this->assertSame(
            [['Sandbox-Agent/1.0'], [FakeOAuthProvider::$userAgent], ['Sandbox-Agent/1.0']],
            Http::recorded()->map(fn (array $pair): array => $pair[0]->header('User-Agent'))->values()->all(),
        );

        // Edit: changes the field and keeps whatever else is stored, and the secret.
        $sandbox->forceFill(['settings' => ['user_agent' => 'Sandbox-Agent/1.0', 'other' => 'kept']])->save();
        Livewire::test(EditOAuthApp::class, ['record' => $sandbox->getKey()])
            ->assertFormSet(['settings.user_agent' => 'Sandbox-Agent/1.0'])
            ->fillForm(['settings.user_agent' => 'Sandbox-Agent/2.0'])
            ->call('save')
            ->assertHasNoFormErrors();
        $sandbox->refresh();
        $this->assertSame(['user_agent' => 'Sandbox-Agent/2.0', 'other' => 'kept'], $sandbox->settings);
        $this->assertSame('s-secret', $sandbox->client_secret);

        // An app with nothing stored edits from the default, so Save needs no typing.
        Livewire::test(EditOAuthApp::class, ['record' => $production->getKey()])
            ->assertFormSet(['settings.user_agent' => FakeOAuthProvider::$userAgent])
            ->fillForm(['client_id' => 'p-client-2'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(['user_agent' => FakeOAuthProvider::$userAgent], $production->refresh()->settings);
        $production->forceFill(['settings' => null])->save();

        // View shows it; the default when nothing is stored.
        Livewire::test(ViewOAuthApp::class, ['record' => $sandbox->getKey()])->assertSee('Sandbox-Agent/2.0');
        Livewire::test(ViewOAuthApp::class, ['record' => $production->getKey()])->assertSee(FakeOAuthProvider::$userAgent);

        // Providers without app fields show and store none.
        $microsoft = OAuthClient::for('microsoft');
        Livewire::test(EditOAuthApp::class, ['record' => $microsoft->getKey()])
            ->assertFormFieldDoesNotExist('settings.user_agent')
            ->fillForm(['client_id' => 'ms-new'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertNull($microsoft->refresh()->settings);
        Livewire::test(ViewOAuthApp::class, ['record' => $microsoft->getKey()])->assertDontSee('User-Agent');
    }

    public function test_deleting_an_oauth_app_says_who_uses_it_and_keeps_their_tokens(): void
    {
        $this->asSuperAdmin();
        $this->connected($this->account());
        $microsoft = OAuthClient::for('microsoft');

        Livewire::test(EditOAuthApp::class, ['record' => $microsoft->getKey()])
            ->mountAction('delete')
            ->assertMountedActionModalSee('One connected account uses this app.')
            ->callMountedAction();

        $this->assertNull(OAuthClient::for('microsoft'));
        $this->assertSame(1, OAuthToken::query()->count());

        $google = OAuthClient::for('google');
        Livewire::test(ListOAuthApps::class)
            ->callAction(TestAction::make('delete')->table($google));

        $this->assertSame(0, OAuthClient::query()->count());
    }

    public function test_an_oauth_app_whose_provider_is_gone_stays_listed_as_unavailable(): void
    {
        $this->asSuperAdmin();
        $gone = OAuthClient::create(['provider' => 'gone', 'environment' => 'sandbox', 'client_id' => 'old-client']);

        Livewire::test(ListOAuthApps::class)
            ->assertCanSeeTableRecords(OAuthClient::query()->get())
            ->assertSee('old-client');

        Livewire::test(ViewOAuthApp::class, ['record' => $gone->getKey()])
            ->assertSee('This provider is not available')
            ->assertSee('gone');

        Livewire::test(EditOAuthApp::class, ['record' => $gone->getKey()])
            ->fillForm(['client_id' => 'new-client'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('new-client', $gone->refresh()->client_id);
    }

    public function test_oauth_apps_change_nothing_in_demo_mode(): void
    {
        OAuthProviders::register(FakeOAuthProvider::class);
        $this->asSuperAdmin();
        $microsoft = OAuthClient::for('microsoft');

        config(['demo.enabled' => true]);

        Livewire::test(CreateOAuthApp::class)
            ->fillForm(['provider' => 'fake'])
            ->fillForm(['environment' => 'sandbox', 'client_id' => 's-client', 'client_secret' => 's-secret'])
            ->call('create')
            ->assertNotified('Unavailable in demo mode');
        $this->assertNull(OAuthClient::for('fake', 'sandbox'));

        Livewire::test(EditOAuthApp::class, ['record' => $microsoft->getKey()])
            ->fillForm(['client_id' => 'changed'])
            ->call('save')
            ->assertNotified('Unavailable in demo mode');
        $this->assertSame('ms-client', $microsoft->refresh()->client_id);

        Livewire::test(EditOAuthApp::class, ['record' => $microsoft->getKey()])->callAction('delete');
        Livewire::test(ViewOAuthApp::class, ['record' => $microsoft->getKey()])->callAction('delete');
        Livewire::test(ListOAuthApps::class)->callAction(TestAction::make('delete')->table($microsoft));

        $this->assertSame(2, OAuthClient::query()->count());
    }
}
