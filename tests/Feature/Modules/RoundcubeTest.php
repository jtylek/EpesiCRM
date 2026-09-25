<?php

namespace Tests\Feature\Modules;

use App\Filament\Administration\Resources\Modules\Pages\ListModules;
use App\Models\Module;
use App\Models\User;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Models\MailAddress;
use Epesi\Modules\Mail\Services\ContactMatcher;
use Epesi\Modules\Mail\Services\Imap\MailboxFactory;
use Epesi\Modules\Mail\Services\MailArchiver;
use Epesi\Modules\Roundcube\Filament\Pages\Mailbox;
use Epesi\Modules\Roundcube\Roundcube;
use Epesi\Modules\Roundcube\Services\RoundcubeConfigWriter;
use Epesi\Modules\Roundcube\Services\RoundcubeInstaller;
use Epesi\Modules\Roundcube\Services\TicketIssuer;
use epesi_archive_matcher;
use epesi_sso_ticket;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Mockery\MockInterface;
use PDO;
use Tests\Concerns\SignsInUsers;
use Tests\Fakes\Eml;
use Tests\Fakes\FakeMailbox;
use Tests\TestCase;

class RoundcubeTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected User $user;

    protected string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        require_once base_path('modules/Epesi/Roundcube/roundcube-plugins/epesi_sso/epesi_sso_ticket.php');
        require_once base_path('modules/Epesi/Roundcube/roundcube-plugins/epesi_archive/epesi_archive_matcher.php');

        $this->user = $this->userWithRole('employee', ['name' => 'Eli', 'email' => 'eli@ourcompany.test']);
        $this->actingAs($this->user);

        // Never the real storage/roundcube or public/roundcube.
        $this->sandbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'epesi-roundcube-'.bin2hex(random_bytes(4));
        config([
            'epesi-roundcube.path' => $this->sandbox.DIRECTORY_SEPARATOR.'roundcube',
            'epesi-roundcube.public_path' => $this->sandbox.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'roundcube',
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
            'name' => 'Work',
            'email' => 'me@ourcompany.test',
            'imap_host' => 'imap.test',
            'imap_password' => 'secret',
            'smtp_host' => 'smtp.test',
            ...$attributes,
        ]);
    }

    protected function pretendInstalled(): void
    {
        File::ensureDirectoryExists(Roundcube::publicPath());
        File::put(Roundcube::publicPath().DIRECTORY_SEPARATOR.'index.php', '<?php');
        File::ensureDirectoryExists(Roundcube::path('config'));
        File::put(Roundcube::path('config'.DIRECTORY_SEPARATOR.'config.inc.php'), '<?php');
    }

    /** epesi_sso_ticket::redeem() over this test's database, as the plugin runs it over Roundcube's. */
    protected function redeem(string $token, ?int $now = null): ?array
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
            $now,
        );
    }

    public function test_a_ticket_carries_the_account_encrypted_and_logs_in_once(): void
    {
        $account = $this->account(['smtp_security' => 'tls', 'archive_folder' => 'CRM Archive']);

        $token = app(TicketIssuer::class)->issue($account, $this->user);

        $row = DB::table('epesi_roundcube_tickets')->sole();
        $this->assertSame(hash('sha256', $token), $row->token, 'only the hash is stored');
        $this->assertStringNotContainsString('secret', $row->payload);

        $login = $this->redeem($token);

        $this->assertSame(['host' => 'ssl://imap.test:993', 'user' => 'me@ourcompany.test', 'pass' => 'secret'], $login['imap'],
            'an empty IMAP login means the e-mail address');
        $this->assertSame(['host' => 'tls://smtp.test:587', 'user' => 'me@ourcompany.test', 'pass' => 'secret'], $login['smtp'],
            'empty SMTP credentials mean the IMAP ones');
        $this->assertSame('CRM Archive', $login['archive_folder']);
        $this->assertSame('Eli', $login['name']);
        $this->assertFalse($login['sees_all']);

        $this->assertNull($this->redeem($token), 'a ticket logs in once');
        $this->assertSame(0, DB::table('epesi_roundcube_tickets')->count());
    }

    public function test_an_expired_ticket_is_refused_and_later_purged(): void
    {
        $account = $this->account();
        $token = app(TicketIssuer::class)->issue($account, $this->user);

        $this->assertNull($this->redeem($token, now()->addMinutes(2)->getTimestamp()));

        $this->travel(2)->minutes();
        app(TicketIssuer::class)->issue($account, $this->user);

        $this->assertSame(1, DB::table('epesi_roundcube_tickets')->count(), 'the expired one is gone');
    }

    public function test_the_page_explains_what_is_missing(): void
    {
        $this->get(Mailbox::getUrl())->assertOk()->assertSee('Ask your administrator to install it.')->assertDontSee('Download and install Roundcube');

        $this->actingAs($this->userWithRole('super_admin'));
        $this->get(Mailbox::getUrl())->assertOk()->assertSee('Download and install Roundcube');
        $this->actingAs($this->user);

        $this->pretendInstalled();
        $this->get(Mailbox::getUrl())->assertOk()->assertSee('Set up a mail account');

        $this->account(['imap_host' => null]);
        $this->get(Mailbox::getUrl())->assertOk()->assertSee('Set up a mail account', 'an account that only sends is no mailbox');
    }

    public function test_an_administrator_installs_roundcube_from_the_page(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        $this->mock(RoundcubeInstaller::class, fn (MockInterface $mock) => $mock->shouldReceive('install')->once());

        Livewire::test(Mailbox::class)
            ->callAction('installRoundcube')
            ->assertNotified(__('Roundcube is installed'));

        $this->assertTrue(Module::query()->where('module_id', 'epesi/roundcube')->where('enabled', true)->exists());
    }

    public function test_administration_offers_roundcube_until_it_is_installed(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        $this->mock(RoundcubeInstaller::class, fn (MockInterface $mock) => $mock->shouldReceive('install')->once());
        Filament::setCurrentPanel('administration');

        Livewire::test(ListModules::class)
            ->assertActionVisible('installRoundcube')
            ->callAction('installRoundcube')
            ->assertNotified(__('Roundcube is installed'));

        $this->assertTrue(Module::query()->where('module_id', 'epesi/roundcube')->where('enabled', true)->exists());

        $this->pretendInstalled();
        Livewire::test(ListModules::class)->assertActionHidden('installRoundcube');
    }

    public function test_only_an_administrator_can_install_roundcube(): void
    {
        $this->mock(RoundcubeInstaller::class, fn (MockInterface $mock) => $mock->shouldNotReceive('install'));

        Livewire::test(Mailbox::class)->assertActionHidden('installRoundcube');
    }

    public function test_the_installer_finds_the_php_command_line(): void
    {
        $this->assertSame(PHP_BINARY, app(RoundcubeInstaller::class)->php());
    }

    public function test_the_page_frames_roundcube_logged_in_with_a_ticket(): void
    {
        $this->pretendInstalled();
        $account = $this->account();

        $page = Livewire::test(Mailbox::class)->assertSet('accountId', $account->id);

        $this->assertStringContainsString('/roundcube/index.php?_task=login&_epesi_ticket=', $page->get('frameUrl'));
        $this->assertSame(1, DB::table('epesi_roundcube_tickets')->where('account_id', $account->id)->count());
    }

    public function test_switching_accounts_logs_the_frame_in_again_but_only_to_your_own(): void
    {
        $this->pretendInstalled();
        $work = $this->account();
        $private = $this->account(['name' => 'Private', 'email' => 'me@home.test']);
        $theirs = MailAccount::create([
            'user_id' => $this->userWithRole('employee')->id,
            'name' => 'Theirs',
            'email' => 'them@ourcompany.test',
            'imap_host' => 'imap.test',
        ]);

        Livewire::test(Mailbox::class)
            ->assertSet('accountId', $work->id)
            ->call('switchAccount', $private->id)
            ->assertSet('accountId', $private->id)
            ->assertDispatched('epesi-roundcube-load')
            ->call('switchAccount', $theirs->id)
            ->assertSet('accountId', $private->id)
            ->call('relogin')
            ->assertDispatched('epesi-roundcube-load');

        $this->assertSame(0, DB::table('epesi_roundcube_tickets')->where('account_id', $theirs->id)->count());
    }

    public function test_the_archive_button_archives_the_archive_folder_straight_away(): void
    {
        $this->pretendInstalled();
        $account = $this->account();
        $server = new FakeMailbox(['CRM Archive' => [1 => Eml::make()]]);
        $this->app->instance(MailboxFactory::class, new class($server) extends MailboxFactory
        {
            public function __construct(private FakeMailbox $server) {}

            public function make(MailAccount $account): FakeMailbox
            {
                return $this->server;
            }
        });

        Livewire::test(Mailbox::class)
            ->call('archived')
            ->assertNotified('1 message archived');

        $this->assertSame(1, Mail::where('account_id', $account->id)->count());
    }

    /**
     * The Archive button warns when epesi_archive_matcher finds nothing; the
     * linking afterwards is ContactMatcher + MailArchiver's. They must agree.
     */
    public function test_the_archive_check_agrees_with_what_archiving_links(): void
    {
        $own = Contact::create(['first_name' => 'Eli', 'last_name' => 'Employee', 'email' => 'me@ourcompany.test']);
        $own->forceFill(['user_id' => $this->user->id])->save();
        MailAddress::create(['addressable_type' => 'contact', 'addressable_id' => $own->id, 'email' => 'eli@home.test']);
        $this->user->refresh();

        $ann = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer', 'email' => 'ann@customer.test']);
        MailAddress::create(['addressable_type' => 'contact', 'addressable_id' => $ann->id, 'email' => 'ann.private@customer.test']);
        Company::create(['company_name' => 'Customer Ltd', 'email' => 'sales@customer.test']);

        $secret = Contact::create(['first_name' => 'Sam', 'last_name' => 'Secret', 'permission' => 2]);
        $secret->forceFill(['created_by' => $this->userWithRole('employee')->id])->save();
        MailAddress::create(['addressable_type' => 'contact', 'addressable_id' => $secret->id, 'email' => 'sam@elsewhere.test']);

        $gone = Contact::create(['first_name' => 'Gone', 'last_name' => 'Away', 'email' => 'gone@customer.test']);
        MailAddress::create(['addressable_type' => 'contact', 'addressable_id' => $gone->id, 'email' => 'gone.too@customer.test']);
        $gone->delete();

        $pdo = DB::connection()->getPdo();
        $selectOne = function (string $sql, array $params) use ($pdo): ?array {
            $statement = $pdo->prepare($sql);
            $statement->execute($params);

            return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
        };

        foreach ([
            'a contact, whatever the case' => [['Ann@Customer.test'], true],
            'a company' => [['sales@customer.test'], true],
            "a contact's extra address" => [['ann.private@customer.test'], true],
            "someone else's private contact, by its extra address" => [['sam@elsewhere.test'], true],
            'a deleted contact' => [['gone@customer.test'], false],
            "a deleted contact's extra address" => [['gone.too@customer.test'], false],
            'your own contact, as in Epesi' => [['me@ourcompany.test'], true],
            'your own extra address and a stranger' => [['eli@home.test', 'stranger@nowhere.test'], true],
            'a stranger and a Cc to a contact' => [['stranger@nowhere.test', 'ann@customer.test'], true],
            'only strangers' => [['stranger@nowhere.test', 'someone@else.test'], false],
            'no addresses' => [[], false],
        ] as $case => [$emails, $expected]) {
            $this->assertSame($expected, app(ContactMatcher::class)->match($emails)->isNotEmpty(), "archiving links: {$case}");
            $this->assertSame($expected, epesi_archive_matcher::found($emails, $selectOne), "the Archive check: {$case}");
        }
    }

    /** Epesi's "Message already archived", by anyone; a deleted copy doesn't count. */
    public function test_the_archive_check_knows_what_is_already_archived(): void
    {
        $colleague = $this->userWithRole('employee');
        $mail = app(MailArchiver::class)->archive(Eml::make(messageId: 'offer@customer.test'), $colleague);

        $pdo = DB::connection()->getPdo();
        $selectOne = function (string $sql, array $params) use ($pdo): ?array {
            $statement = $pdo->prepare($sql);
            $statement->execute($params);

            return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
        };

        $this->assertSame(['<offer@customer.test>'], epesi_archive_matcher::archived(['<offer@customer.test>', '<new@customer.test>', ''], $selectOne));

        $mail->delete();
        $this->assertSame([], epesi_archive_matcher::archived(['<offer@customer.test>'], $selectOne));
    }

    public function test_logging_out_ends_the_roundcube_session(): void
    {
        Schema::create('rc_session', function (Blueprint $table) {
            $table->string('sess_id')->primary();
            $table->text('vars')->nullable();
        });
        DB::table('rc_session')->insert([['sess_id' => 'mine'], ['sess_id' => 'someone-else']]);

        $this->withUnencryptedCookie(Roundcube::SESSION_COOKIE, 'mine')
            ->post(route('filament.main.auth.logout'))
            ->assertRedirect();

        $this->assertSame(['someone-else'], DB::table('rc_session')->pluck('sess_id')->all());
    }

    public function test_the_generated_config_comes_from_laravels(): void
    {
        config([
            'app.url' => 'http://localhost/epesi-laravel',
            'database.connections.crm' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => '3306',
                'database' => 'epesi',
                'username' => 'me@host',
                'password' => 'p:ss/word',
            ],
        ]);

        $this->assertSame('mysql://me%40host:p%3Ass%2Fword@127.0.0.1:3306/epesi', app(RoundcubeConfigWriter::class)->dsn('crm'));

        $settings = app(RoundcubeConfigWriter::class)->settings();

        $this->assertStringStartsWith('sqlite:///', $settings['db_dsnw'], "the app's own database");
        $this->assertSame('rc_', $settings['db_prefix']);
        $this->assertSame('/epesi-laravel/', $settings['session_path']);
        $this->assertSame(Roundcube::SESSION_COOKIE, $settings['session_name']);
        $this->assertSame(bin2hex(Roundcube::key('sso')), $settings['epesi_sso_key']);
        $this->assertSame(32, strlen($settings['des_key']));
        $this->assertSame($settings['des_key'], app(RoundcubeConfigWriter::class)->settings()['des_key'], 'the same on every run');
        $this->assertStringNotContainsString(config('app.key'), var_export($settings, true));
        $this->assertNull($settings['imap_conn_options'], 'certificates are checked');

        config(['epesi-mail.imap_validate_cert' => false]);
        $this->assertFalse(app(RoundcubeConfigWriter::class)->settings()['imap_conn_options']['ssl']['verify_peer']);
    }

    public function test_the_installer_refuses_a_download_with_the_wrong_checksum(): void
    {
        Http::fake(['*' => Http::response('not a tarball')]);

        $this->artisan('roundcube:install')
            ->expectsOutputToContain("checksum doesn't match")
            ->assertFailed();

        $this->assertDirectoryDoesNotExist(Roundcube::path());

        $this->artisan('roundcube:install', ['--release' => '1.7.5'])
            ->expectsOutputToContain('--release needs --sha256')
            ->assertFailed();
    }
}
