<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use Epesi\Modules\Mail\Filament\Resources\MailAccounts\Pages\CreateMailAccount;
use Epesi\Modules\Mail\Filament\Resources\MailAccounts\Pages\EditMailAccount;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\Imap\WebklexMailbox;
use Epesi\Modules\Mail\Services\SmtpTransportFactory;
use Epesi\Modules\OAuth\Exceptions\OAuthException;
use Epesi\Modules\OAuth\Models\OAuthClient;
use Epesi\Modules\OAuth\Models\OAuthToken;
use Epesi\Modules\Roundcube\Filament\Pages\Mailbox;
use Epesi\Modules\Roundcube\Roundcube;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use ReflectionProperty;
use Symfony\Component\Mailer\Transport\Smtp\Auth\XOAuth2Authenticator;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Mail accounts that sign in with Microsoft 365 or Google (XOAUTH2) through
 * the OAuth module's tokens.
 */
class MailOAuthTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->user = $this->userWithRole('employee');
        $this->actingAs($this->user);

        OAuthClient::create(['provider' => 'microsoft', 'client_id' => 'ms-client', 'client_secret' => 'ms-secret']);
    }

    protected function account(array $attributes = []): MailAccount
    {
        return MailAccount::create([
            'user_id' => $this->user->id,
            'name' => 'Office',
            'email' => 'me@contoso.test',
            'auth_method' => 'microsoft',
            'imap_host' => 'outlook.office365.com',
            'smtp_host' => 'smtp.office365.com',
            'smtp_port' => 587,
            ...$attributes,
        ]);
    }

    protected function connect(MailAccount $account): OAuthToken
    {
        return $account->oauthTokens()->create([
            'provider' => 'microsoft',
            'access_token' => 'the-access-token',
            'refresh_token' => 'the-refresh-token',
            'expires_at' => now()->addHour(),
        ]);
    }

    public function test_a_new_account_signs_in_with_its_password(): void
    {
        $account = MailAccount::create(['user_id' => $this->user->id, 'name' => 'Home', 'email' => 'me@home.test', 'imap_host' => 'imap.test', 'imap_password' => 'pw']);

        $this->assertSame('password', $account->fresh()->auth_method);
        $this->assertFalse($account->usesOAuth());

        $config = (new WebklexMailbox($account))->clientConfig();
        $this->assertSame('pw', $config['password']);
        $this->assertNull($config['authentication']);
    }

    public function test_imap_logs_in_with_xoauth2_and_the_access_token(): void
    {
        $account = $this->account();
        $this->connect($account);

        $config = (new WebklexMailbox($account))->clientConfig();

        $this->assertSame('oauth', $config['authentication']);
        $this->assertSame('me@contoso.test', $config['username']);
        $this->assertSame('the-access-token', $config['password']);
    }

    public function test_smtp_authenticates_with_xoauth2_only(): void
    {
        $account = $this->account(['smtp_auth' => false]);
        $this->connect($account);

        $transport = app(SmtpTransportFactory::class)->forAccount($account);

        $this->assertInstanceOf(EsmtpTransport::class, $transport);
        $this->assertSame('me@contoso.test', $transport->getUsername());
        $this->assertSame('the-access-token', $transport->getPassword());

        $authenticators = (new ReflectionProperty(EsmtpTransport::class, 'authenticators'))->getValue($transport);
        $this->assertCount(1, $authenticators);
        $this->assertInstanceOf(XOAuth2Authenticator::class, $authenticators[0]);
    }

    public function test_an_account_not_connected_yet_says_so_instead_of_sending_a_password(): void
    {
        $account = $this->account(['imap_password' => 'old-password']);

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage('Connect the account first');

        app(SmtpTransportFactory::class)->forAccount($account);
    }

    public function test_the_form_hides_passwords_and_offers_connect_for_an_oauth_account(): void
    {
        Filament::setCurrentPanel('user-settings');
        $account = $this->account();

        Livewire::test(EditMailAccount::class, ['record' => $account->getRouteKey()])
            ->assertFormFieldIsHidden('imap_password')
            ->assertFormFieldIsHidden('smtp_password')
            ->assertSee('Not connected yet')
            ->assertSee('Connect account');

        $this->connect($account);

        Livewire::test(EditMailAccount::class, ['record' => $account->getRouteKey()])
            ->assertSee('Connected')
            ->assertSee('Connect again')
            ->assertSee('Disconnect');
    }

    public function test_choosing_microsoft_fills_in_its_servers(): void
    {
        Filament::setCurrentPanel('user-settings');

        Livewire::test(CreateMailAccount::class)
            ->fillForm(['name' => 'Office', 'email' => 'me@contoso.test'])
            ->fillForm(['auth_method' => 'microsoft'])
            ->assertFormSet([
                'imap_host' => 'outlook.office365.com',
                'smtp_host' => 'smtp.office365.com',
                'smtp_port' => 587,
            ])
            ->assertFormFieldIsHidden('imap_password')
            ->assertSee('Save the account, then connect it')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('microsoft', MailAccount::query()->where('email', 'me@contoso.test')->value('auth_method'));
    }

    public function test_only_the_accounts_own_user_may_connect_it(): void
    {
        $account = $this->account();

        $this->assertTrue($account->mayConnectOAuth($this->user));
        $this->assertFalse($account->mayConnectOAuth($this->userWithRole('employee')));
        $this->assertFalse($account->mayConnectOAuth($this->userWithRole('super_admin')));
        $this->assertSame([$this->user->id], collect($account->oauthRecipients())->pluck('id')->all());
    }

    public function test_deleting_the_account_deletes_its_token(): void
    {
        $account = $this->account();
        $this->connect($account);

        $account->delete();

        $this->assertSame(0, OAuthToken::query()->count());
    }

    public function test_the_mailbox_page_frames_roundcube_for_an_oauth_account(): void
    {
        $sandbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'epesi-roundcube-'.bin2hex(random_bytes(4));
        config([
            'epesi-roundcube.path' => $sandbox.DIRECTORY_SEPARATOR.'roundcube',
            'epesi-roundcube.public_path' => $sandbox.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'epesi-webmail',
        ]);
        File::ensureDirectoryExists(Roundcube::publicPath());
        File::put(Roundcube::publicPath().DIRECTORY_SEPARATOR.'index.php', '<?php');
        File::ensureDirectoryExists(Roundcube::path('config'));
        File::put(Roundcube::path('config'.DIRECTORY_SEPARATOR.'config.inc.php'), '<?php');
        File::ensureDirectoryExists(Roundcube::path('plugins'.DIRECTORY_SEPARATOR.'epesi_sso'));
        File::put(Roundcube::path('plugins'.DIRECTORY_SEPARATOR.'epesi_sso'.DIRECTORY_SEPARATOR.'epesi_sso.php'), '<?php');

        try {
            $account = $this->account();
            $this->connect($account);

            // XOAUTH2 in Roundcube: RoundcubeOAuthTest.
            $frameUrl = Livewire::test(Mailbox::class)
                ->assertSet('accountId', $account->id)
                ->assertSet('oauthError', null)
                ->get('frameUrl');

            $this->assertStringContainsString(Roundcube::TICKET_PARAMETER.'=', (string) $frameUrl);
        } finally {
            File::deleteDirectory($sandbox);
        }
    }
}
