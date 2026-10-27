<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\OAuth\Enums\TokenStatus;
use Epesi\Modules\OAuth\Models\OAuthClient;
use Epesi\Modules\OAuth\Models\OAuthToken;
use Epesi\Modules\Roundcube\Filament\Pages\Mailbox;
use Epesi\Modules\Roundcube\Roundcube;
use Epesi\Modules\Roundcube\Services\OAuthGrants;
use Epesi\Modules\Roundcube\Services\TicketIssuer;
use epesi_sso_oauth;
use epesi_sso_ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use PDO;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Roundcube logged in to an OAuth (XOAUTH2) mail account: the ticket carries
 * an access token and a grant, and epesi_sso asks Epesi's token endpoint for
 * each new token with it. The plugin's own Roundcube hooks aren't run here,
 * only its plain-PHP parts (epesi_sso_ticket, epesi_sso_oauth), against the
 * real endpoint.
 */
class RoundcubeOAuthTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected User $user;

    protected string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        require_once base_path('modules/Epesi/Roundcube/roundcube-plugins/epesi_sso/epesi_sso_oauth.php');

        Http::preventStrayRequests();

        $this->user = $this->userWithRole('employee', ['name' => 'Eli']);
        $this->actingAs($this->user);

        OAuthClient::create(['provider' => 'microsoft', 'client_id' => 'ms-client', 'client_secret' => 'ms-secret']);

        $this->sandbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'epesi-roundcube-'.bin2hex(random_bytes(4));
        config([
            'epesi-roundcube.path' => $this->sandbox.DIRECTORY_SEPARATOR.'roundcube',
            'epesi-roundcube.public_path' => $this->sandbox.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'epesi-webmail',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    protected function account(array $attributes = []): MailAccount
    {
        return MailAccount::create([
            'user_id' => $this->user->id,
            'name' => 'Office',
            'email' => 'me@contoso.test',
            'auth_method' => 'microsoft',
            'imap_host' => 'outlook.office365.com',
            'imap_security' => 'ssl',
            'smtp_host' => 'smtp.office365.com',
            'smtp_security' => 'tls',
            'smtp_port' => 587,
            ...$attributes,
        ]);
    }

    protected function connect(MailAccount $account, array $attributes = []): OAuthToken
    {
        return $account->oauthTokens()->create([
            'provider' => 'microsoft',
            'access_token' => 'the-access-token',
            'refresh_token' => 'the-refresh-token',
            'expires_at' => now()->addHour(),
            ...$attributes,
        ]);
    }

    protected function pretendInstalled(): void
    {
        File::ensureDirectoryExists(Roundcube::publicPath());
        File::put(Roundcube::publicPath().DIRECTORY_SEPARATOR.'index.php', '<?php');
        File::ensureDirectoryExists(Roundcube::path('config'));
        File::put(Roundcube::path('config'.DIRECTORY_SEPARATOR.'config.inc.php'), '<?php');
        File::ensureDirectoryExists(Roundcube::path('plugins'.DIRECTORY_SEPARATOR.'epesi_sso'));
        File::put(Roundcube::path('plugins'.DIRECTORY_SEPARATOR.'epesi_sso'.DIRECTORY_SEPARATOR.'epesi_sso.php'), '<?php');
    }

    /** The login epesi_sso redeems from a ticket for $account. */
    protected function login(MailAccount $account): array
    {
        return $this->redeem(app(TicketIssuer::class)->issue($account, $this->user));
    }

    protected function redeem(string $token): array
    {
        $pdo = DB::connection()->getPdo();

        return epesi_sso_ticket::redeem(
            $token,
            Roundcube::key('sso'),
            function (string $sql, array $params) use ($pdo): ?array {
                $statement = $pdo->prepare($sql);
                $statement->execute($params);

                return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
            },
            function (string $sql, array $params) use ($pdo): int {
                $statement = $pdo->prepare($sql);
                $statement->execute($params);

                return $statement->rowCount();
            },
        );
    }

    /** epesi_sso_oauth::fetch() against this app's endpoint, as the plugin posts to it from Roundcube. */
    protected function fetch(string $grant, ?string $url = null): array
    {
        return epesi_sso_oauth::fetch(
            $url ?? route('epesi.roundcube.oauth-token'),
            $grant,
            Roundcube::key('sso'),
            function (string $url, array $form): array {
                $response = $this->post($url, $form);

                return [$response->getStatusCode(), (string) $response->getContent()];
            },
        );
    }

    public function test_the_ticket_logs_in_with_xoauth2_and_carries_a_grant(): void
    {
        $account = $this->account();
        $token = $this->connect($account);

        $issued = app(TicketIssuer::class)->issue($account, $this->user);
        $this->assertStringNotContainsString('the-access-token', DB::table('epesi_roundcube_tickets')->value('payload'));

        $login = $this->redeem($issued);

        $this->assertSame(['host' => 'ssl://outlook.office365.com:993', 'user' => 'me@contoso.test', 'pass' => 'the-access-token'], $login['imap'],
            'the access token is the IMAP password');
        $this->assertSame(['host' => 'tls://smtp.office365.com:587', 'user' => 'me@contoso.test', 'pass' => ''], $login['smtp'],
            'SMTP authenticates with the session\'s token, whatever smtp_auth says');

        $this->assertSame(route('epesi.roundcube.oauth-token'), $login['oauth']['url']);
        $this->assertSame($token->expires_at->getTimestamp(), $login['oauth']['expires_at']);
        $this->assertFileExists($login['oauth']['ca']);

        $grant = DB::table(OAuthGrants::TABLE)->sole();
        $this->assertSame(hash('sha256', $login['oauth']['grant']), $grant->token, 'only the grant\'s hash is stored');
        $this->assertSame($account->id, (int) $grant->account_id);
    }

    public function test_a_password_accounts_ticket_is_unchanged(): void
    {
        $account = $this->account(['auth_method' => 'password', 'imap_password' => 'secret', 'smtp_auth' => true]);

        $login = $this->login($account);

        $this->assertSame('secret', $login['imap']['pass']);
        $this->assertSame('secret', $login['smtp']['pass']);
        $this->assertNull($login['oauth']);
        $this->assertSame(0, DB::table(OAuthGrants::TABLE)->count());
    }

    public function test_the_grant_fetches_the_current_token_and_its_expiry_moves_forward(): void
    {
        $account = $this->account();
        $this->connect($account);
        $grant = $this->login($account)['oauth']['grant'];

        DB::table(OAuthGrants::TABLE)->update(['expires_at' => now()->addMinute()->getTimestamp()]);

        $result = $this->fetch($grant);

        $this->assertSame('the-access-token', $result['access_token']);
        $this->assertGreaterThan(now()->addMinutes(59)->getTimestamp(), (int) DB::table(OAuthGrants::TABLE)->value('expires_at'));
    }

    public function test_a_token_about_to_expire_is_renewed_by_epesi_not_roundcube(): void
    {
        $account = $this->account();
        $this->connect($account);
        $grant = $this->login($account)['oauth']['grant'];

        $account->oauthToken('microsoft')->forceFill(['expires_at' => now()->addMinute()])->save();
        Http::fake(['login.microsoftonline.com/*' => Http::response([
            'access_token' => 'the-new-access-token',
            'refresh_token' => 'the-new-refresh-token',
            'expires_in' => 3600,
        ])]);

        $result = $this->fetch($grant);

        $this->assertSame('the-new-access-token', $result['access_token']);
        $this->assertEqualsWithDelta(now()->addHour()->getTimestamp(), $result['expires_at'], 5);
        $this->assertSame('the-new-refresh-token', $account->oauthToken('microsoft')->refresh_token, 'Epesi stored the rotated refresh token');
        Http::assertSentCount(1);
    }

    public function test_the_endpoint_answers_encrypted_and_never_with_a_refresh_token(): void
    {
        $account = $this->account();
        $this->connect($account);
        $grant = $this->login($account)['oauth']['grant'];

        $response = $this->post(route('epesi.roundcube.oauth-token'), ['grant' => $grant]);

        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringNotContainsString('the-access-token', $response->getContent());
        $this->assertStringNotContainsString('the-refresh-token', $response->getContent());
        $this->assertSame(['access_token', 'expires_at'], array_keys(epesi_sso_ticket::decrypt($response->json('payload'), Roundcube::key('sso'))));
    }

    public function test_the_endpoint_needs_no_session_or_csrf_token(): void
    {
        $this->assertSame([], Route::getRoutes()->getByName('epesi.roundcube.oauth-token')->gatherMiddleware(),
            'Roundcube posts from the server, with no Laravel session');
        $this->assertSame(['POST'], Route::getRoutes()->getByName('epesi.roundcube.oauth-token')->methods());
    }

    public function test_an_unknown_or_expired_grant_is_refused(): void
    {
        $account = $this->account();
        $this->connect($account);
        $grant = $this->login($account)['oauth']['grant'];

        $this->assertSame(['error' => 'refused'], $this->fetch('not-a-grant'));
        $this->assertSame(['error' => 'refused'], $this->fetch(''));

        DB::table(OAuthGrants::TABLE)->update(['expires_at' => now()->subSecond()->getTimestamp()]);

        $this->assertSame(['error' => 'refused'], $this->fetch($grant));
    }

    public function test_a_grant_ends_when_its_account_or_user_may_no_longer_sign_in_this_way(): void
    {
        $account = $this->account();
        $this->connect($account);

        $grant = $this->login($account)['oauth']['grant'];
        $account->update(['auth_method' => 'password']);
        $this->assertSame(['error' => 'refused'], $this->fetch($grant));
        $this->assertSame(0, DB::table(OAuthGrants::TABLE)->count(), 'a refused grant is deleted');

        $account->update(['auth_method' => 'microsoft']);
        $grant = $this->login($account)['oauth']['grant'];
        $this->user->update(['active' => false]);
        $this->assertSame(['error' => 'refused'], $this->fetch($grant));
    }

    public function test_a_connection_to_reconnect_ends_the_grant_but_a_passing_failure_keeps_it(): void
    {
        $account = $this->account();
        $token = $this->connect($account);
        $grant = $this->login($account)['oauth']['grant'];

        // The provider is down: Roundcube carries on with its token and asks again.
        $token->forceFill(['expires_at' => now()->addMinute()])->save();
        Http::fake(['login.microsoftonline.com/*' => Http::response(['error' => 'temporarily_unavailable'], 503)]);

        $this->assertSame(['error' => 'unavailable'], $this->fetch($grant));
        $this->assertSame(1, DB::table(OAuthGrants::TABLE)->count());

        $token->forceFill(['status' => TokenStatus::NeedsReconnect])->save();

        $this->assertSame(['error' => 'refused'], $this->fetch($grant));
        $this->assertSame(0, DB::table(OAuthGrants::TABLE)->count());
    }

    public function test_a_session_asks_shortly_before_its_token_expires(): void
    {
        $now = 1_000_000;

        $this->assertFalse(epesi_sso_oauth::due(['expires_at' => $now + 600], $now));
        $this->assertTrue(epesi_sso_oauth::due(['expires_at' => $now + epesi_sso_oauth::MARGIN], $now));
        $this->assertFalse(epesi_sso_oauth::due(['expires_at' => null], $now), 'a token without an expiry is never renewed');
        $this->assertLessThan((int) config('epesi-oauth.refresh_ahead'), epesi_sso_oauth::MARGIN,
            'what Epesi hands out must not be due again at once');
    }

    public function test_an_unreachable_or_garbled_endpoint_is_unavailable_not_refused(): void
    {
        $key = Roundcube::key('sso');

        $this->assertSame(['error' => 'unavailable'], epesi_sso_oauth::fetch('https://x.test', 'g', $key, fn () => throw new \RuntimeException('down')));
        $this->assertSame(['error' => 'unavailable'], epesi_sso_oauth::fetch('https://x.test', 'g', $key, fn () => [200, '{"payload":"garbled"}']));
        $this->assertSame(['error' => 'unavailable'], epesi_sso_oauth::fetch('https://x.test', 'g', $key, fn () => [500, '<html>']));
        $this->assertSame(['error' => 'refused'], epesi_sso_oauth::fetch('https://x.test', 'g', $key, fn () => [403, '{"error":"refused"}']));
    }

    public function test_the_internal_url_replaces_only_scheme_and_host(): void
    {
        config(['epesi-roundcube.internal_url' => 'http://127.0.0.1:8080/']);

        $this->assertSame('http://127.0.0.1:8080/'.ltrim(route('epesi.roundcube.oauth-token', absolute: false), '/'), app(OAuthGrants::class)->url());
    }

    public function test_the_mailbox_page_frames_roundcube_for_a_connected_oauth_account(): void
    {
        $this->pretendInstalled();
        $account = $this->account();
        $this->connect($account);

        $page = Livewire::test(Mailbox::class)
            ->assertSet('accountId', $account->id)
            ->assertSet('oauthError', null);

        $this->assertStringContainsString(Roundcube::TICKET_PARAMETER.'=', (string) $page->get('frameUrl'));
        $this->assertStringNotContainsString('the-access-token', (string) $page->get('frameUrl'), 'the token never goes into a URL');
    }

    public function test_the_mailbox_page_says_what_to_do_for_an_oauth_account_without_a_token(): void
    {
        $this->pretendInstalled();
        $account = $this->account();

        Livewire::test(Mailbox::class)
            ->assertSet('frameUrl', null)
            ->assertSee('Connect the account first')
            ->assertSee('Mail accounts');

        $this->connect($account, ['status' => TokenStatus::NeedsReconnect]);

        Livewire::test(Mailbox::class)
            ->assertSet('frameUrl', null)
            ->assertSee('Connect the account again');

        $this->assertSame(0, DB::table('epesi_roundcube_tickets')->count(), 'nothing is stored for a login that can\'t work');
        $this->assertSame(0, DB::table(OAuthGrants::TABLE)->count());
    }

    public function test_relogin_for_an_account_that_must_be_reconnected_shows_why(): void
    {
        $this->pretendInstalled();
        $account = $this->account();
        $token = $this->connect($account);

        $page = Livewire::test(Mailbox::class);
        $token->forceFill(['status' => TokenStatus::NeedsReconnect])->save();

        $page->call('relogin')
            ->assertSet('frameUrl', null)
            ->assertRedirect(Mailbox::getUrl(['account' => $account->id]));
    }
}
