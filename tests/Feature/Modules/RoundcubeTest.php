<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use App\Filament\Administration\Resources\Modules\Pages\ListModules;
use App\Models\Module;
use App\Models\User;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\Mail\Filament\Actions\LinkRecordAction;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\ContactMatcher;
use Epesi\Modules\Mail\Services\Imap\MailboxFactory;
use Epesi\Modules\Mail\Services\MailArchiver;
use Epesi\Modules\Roundcube\Filament\Pages\Mailbox;
use Epesi\Modules\Roundcube\Roundcube;
use Epesi\Modules\Roundcube\Services\RoundcubeConfigWriter;
use Epesi\Modules\Roundcube\Services\RoundcubeInstaller;
use Epesi\Modules\Roundcube\Services\RoundcubeSchema;
use Epesi\Modules\Roundcube\Services\TicketIssuer;
use epesi_archive_matcher;
use epesi_sso_ticket;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
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
        File::ensureDirectoryExists(Roundcube::path('plugins'.DIRECTORY_SEPARATOR.'epesi_sso'));
        File::put(Roundcube::path('plugins'.DIRECTORY_SEPARATOR.'epesi_sso'.DIRECTORY_SEPARATOR.'epesi_sso.php'), '<?php');
    }

    /** A downloaded Roundcube's files that publishing touches, and module plugins to copy, all in the sandbox. */
    protected function fakeDownload(): void
    {
        $webRoot = Roundcube::path('public_html');
        File::ensureDirectoryExists(Roundcube::path('program'));
        File::ensureDirectoryExists(Roundcube::path('plugins'));
        File::ensureDirectoryExists($webRoot);
        File::put($webRoot.DIRECTORY_SEPARATOR.'index.php', "<?php echo 'index in '.basename(__DIR__);");
        File::put($webRoot.DIRECTORY_SEPARATOR.'static.php', "<?php echo 'static in '.basename(__DIR__);");
        File::put($webRoot.DIRECTORY_SEPARATOR.'installer.php', '<?php');
        File::put($webRoot.DIRECTORY_SEPARATOR.'.htaccess', "RewriteEngine On\n");
        File::ensureDirectoryExists($this->pluginSource());
        File::put($this->pluginSource('epesi_sso.php'), '<?php // one');
    }

    protected function pluginSource(string $file = ''): string
    {
        return $this->sandbox.DIRECTORY_SEPARATOR.'module-plugins'.DIRECTORY_SEPARATOR.'epesi_sso'.($file === '' ? '' : DIRECTORY_SEPARATOR.$file);
    }

    /** The installer, copying plugins from the sandbox instead of the module's real ones. */
    protected function sandboxedInstaller(): RoundcubeInstaller
    {
        return new class(new Filesystem, new RoundcubeConfigWriter, new RoundcubeSchema, dirname($this->pluginSource())) extends RoundcubeInstaller
        {
            public function __construct(Filesystem $files, RoundcubeConfigWriter $config, RoundcubeSchema $schema, protected string $sources)
            {
                parent::__construct($files, $config, $schema);
            }

            protected function pluginSources(): string
            {
                return $this->sources;
            }

            public function publishNow(): void
            {
                $this->publish(function (string $line): void {});
            }
        };
    }

    /** What an earlier install made: a junction on Windows, a symlink elsewhere. */
    protected function link(string $target, string $link): void
    {
        File::ensureDirectoryExists(dirname($link));
        windows_os() ? File::link($target, $link) : symlink($target, $link);
        clearstatcache();
        $this->assertNotFalse(@lstat($link), "the link {$link} was made");
    }

    protected function entryOutput(string $file): string
    {
        ob_start();

        try {
            include $file;
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
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

    public function test_install_cleanup_retries_a_transient_directory_removal_failure(): void
    {
        $directory = $this->sandbox.DIRECTORY_SEPARATOR.'previous';
        File::ensureDirectoryExists($directory.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'acl');
        File::put($directory.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'acl'.DIRECTORY_SEPARATOR.'acl.php', '<?php');

        $files = new class extends Filesystem
        {
            public ?string $failPath = null;

            public int $deleteAttempts = 0;

            public function deleteDirectory($directory, $preserve = false)
            {
                if ($directory === $this->failPath && ++$this->deleteAttempts === 1) {
                    throw new \RuntimeException('Directory is temporarily busy.');
                }

                return parent::deleteDirectory($directory, $preserve);
            }
        };
        $files->failPath = $directory;

        $installer = new RoundcubeInstaller($files, new RoundcubeConfigWriter, new RoundcubeSchema);
        $deleteInstall = new \ReflectionMethod($installer, 'deleteInstall');
        $deleteInstall->invoke($installer, $directory);

        $this->assertFalse(is_dir($directory));
        $this->assertSame(2, $files->deleteAttempts);
    }

    /** Some web servers refuse to follow links (Apache on Windows, a junction made without admin rights), so none are made. */
    public function test_the_installer_serves_roundcube_through_stubs_and_copies_without_links(): void
    {
        $this->fakeDownload();
        $installer = $this->sandboxedInstaller();

        $installer->publishNow();

        $public = Roundcube::publicPath();
        $this->assertSame('dir', filetype($public), 'a real directory, not a link');
        $this->assertSame(realpath($public), realpath(dirname($public)).DIRECTORY_SEPARATOR.basename($public));
        $this->assertSame('index in public_html', $this->entryOutput($public.DIRECTORY_SEPARATOR.'index.php'),
            'the stub runs Roundcube\'s own index.php, which finds the rest from its own __DIR__');
        $this->assertSame('static in public_html', $this->entryOutput($public.DIRECTORY_SEPARATOR.'static.php'));
        $this->assertStringContainsString("require __DIR__.'/", (string) file_get_contents($public.DIRECTORY_SEPARATOR.'index.php'), 'by a relative path');
        $this->assertStringContainsString('RewriteEngine On', (string) file_get_contents($public.DIRECTORY_SEPARATOR.'.htaccess'));
        $this->assertFileDoesNotExist($public.DIRECTORY_SEPARATOR.'installer.php', 'Roundcube\'s installer stays unreachable');

        $copy = Roundcube::path('plugins'.DIRECTORY_SEPARATOR.'epesi_sso');
        $this->assertSame('dir', filetype($copy));
        $this->assertSame('<?php // one', file_get_contents($copy.DIRECTORY_SEPARATOR.'epesi_sso.php'));

        $this->assertFalse($installer->refreshPlugins(), 'nothing changed');
        File::put($this->pluginSource('epesi_sso.php'), '<?php // two');
        File::put($this->pluginSource('extra.js'), '');
        $this->assertTrue($installer->refreshPlugins(), 'a module update changed the plugin');
        $this->assertSame('<?php // two', file_get_contents($copy.DIRECTORY_SEPARATOR.'epesi_sso.php'));
        $this->assertFileExists($copy.DIRECTORY_SEPARATOR.'extra.js');

        $installer->publishNow();
        $this->assertSame('index in public_html', $this->entryOutput($public.DIRECTORY_SEPARATOR.'index.php'), 'running it again rewrites its own entry');
    }

    public function test_the_installer_replaces_an_earlier_installs_links_without_touching_what_they_point_at(): void
    {
        $this->fakeDownload();
        $webRoot = Roundcube::path('public_html');
        $plugin = Roundcube::path('plugins'.DIRECTORY_SEPARATOR.'epesi_sso');
        $old = dirname(Roundcube::publicPath()).DIRECTORY_SEPARATOR.'roundcube';
        $this->link($webRoot, Roundcube::publicPath());
        $this->link($this->pluginSource(), $plugin);
        $this->link($webRoot, $old);

        $this->sandboxedInstaller()->publishNow();

        $this->assertFileExists($this->pluginSource('epesi_sso.php'), 'the module\'s own plugin is still there');
        $this->assertFileExists($webRoot.DIRECTORY_SEPARATOR.'index.php');
        $this->assertFileExists($webRoot.DIRECTORY_SEPARATOR.'installer.php');
        $this->assertSame('dir', filetype(Roundcube::publicPath()));
        $this->assertSame('dir', filetype($plugin));
        $this->assertSame('index in public_html', $this->entryOutput(Roundcube::publicPath().DIRECTORY_SEPARATOR.'index.php'));
        clearstatcache();
        $this->assertFalse(@lstat($old), 'the link under the old name is gone');
    }

    public function test_the_installer_leaves_a_directory_it_did_not_write_alone(): void
    {
        $this->fakeDownload();
        File::ensureDirectoryExists(Roundcube::publicPath());
        File::put(Roundcube::publicPath().DIRECTORY_SEPARATOR.'index.php', '<?php // someone else\'s');

        try {
            $this->sandboxedInstaller()->publishNow();
            $this->fail('it overwrote the directory');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString("wasn't written by the Roundcube installer", $e->getMessage());
        }

        $this->assertSame('<?php // someone else\'s', file_get_contents(Roundcube::publicPath().DIRECTORY_SEPARATOR.'index.php'));
    }

    public function test_the_page_says_when_the_web_server_cannot_read_roundcube(): void
    {
        $this->pretendInstalled();
        // What the web server sees of an entry it won't follow: something there, but no index.php.
        File::delete(Roundcube::publicPath().DIRECTORY_SEPARATOR.'index.php');

        $this->assertTrue(Roundcube::isUnreadable());
        $this->get(Mailbox::getUrl())->assertOk()
            ->assertSee('the web server can\'t read its files')
            ->assertDontSee('isn\'t installed yet');
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

    /** Roundcube's tables are made from its SQL files over the app's connection: no PHP process, which shared hosts forbid. */
    public function test_the_schema_is_created_then_updated_through_the_database_connection(): void
    {
        $directory = $this->sandbox.DIRECTORY_SEPARATOR.'SQL';
        File::ensureDirectoryExists($directory.DIRECTORY_SEPARATOR.'sqlite');
        File::put($directory.DIRECTORY_SEPARATOR.'sqlite.initial.sql', <<<'SQL'
-- a comment
CREATE TABLE "system" (
  name varchar(64) NOT NULL PRIMARY KEY,
  value text
);

CREATE TABLE "users" (
  user_id integer NOT NULL PRIMARY KEY,
  username varchar(128) NOT NULL
);

CREATE INDEX "users_username_idx" ON "users" (username);

INSERT INTO "system" (name, value) VALUES ('roundcube-version', '2025092300');
SQL);
        File::put($directory.DIRECTORY_SEPARATOR.'sqlite'.DIRECTORY_SEPARATOR.'2020010100.sql', 'ALTER TABLE "users" ADD COLUMN old_column text;');
        File::put($directory.DIRECTORY_SEPARATOR.'sqlite'.DIRECTORY_SEPARATOR.'2026010100.sql', "ALTER TABLE \"users\" ADD COLUMN language varchar(16);\n");

        $schema = new RoundcubeSchema;

        $this->assertStringContainsString('Created', $schema->migrate($directory, 'rcx_'));
        $this->assertTrue(Schema::hasTable('rcx_system'));
        $this->assertTrue(Schema::hasTable('rcx_users'));
        $this->assertSame('2025092300', DB::table('rcx_system')->where('name', 'roundcube-version')->value('value'));
        $this->assertFalse(Schema::hasTable('system'), 'the prefix is applied');

        $this->assertStringContainsString('Updated', $schema->migrate($directory, 'rcx_'));
        $this->assertTrue(Schema::hasColumn('rcx_users', 'language'));
        $this->assertFalse(Schema::hasColumn('rcx_users', 'old_column'), 'an update older than the stored version is skipped');
        $this->assertSame('2026010100', DB::table('rcx_system')->where('name', 'roundcube-version')->value('value'));

        $this->assertStringContainsString('up to date', $schema->migrate($directory, 'rcx_'));
    }

    public function test_the_page_frames_roundcube_logged_in_with_a_ticket(): void
    {
        $this->pretendInstalled();
        $account = $this->account();

        $page = Livewire::test(Mailbox::class)->assertSet('accountId', $account->id);

        $this->assertStringContainsString('/epesi-webmail/index.php?_task=login&_epesi_ticket=', $page->get('frameUrl'));
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

    public function test_the_archive_button_archives_from_the_inbox_then_moves_and_offers_a_link(): void
    {
        $this->pretendInstalled();
        $account = $this->account();
        $server = new FakeMailbox([
            'INBOX' => [
                1 => Eml::make(messageId: 'clicked@x'),
                2 => Eml::make(messageId: 'second@x'),
                3 => Eml::make(messageId: 'not-clicked@x'),
            ],
            'CRM Archive' => [],
        ]);
        $this->app->instance(MailboxFactory::class, new class($server) extends MailboxFactory
        {
            public function __construct(private FakeMailbox $server) {}

            public function make(MailAccount $account): FakeMailbox
            {
                return $this->server;
            }
        });
        $task = Task::create(['title' => 'Prepare the offer', 'permission' => RecordPermission::Public]);
        $company = Company::create(['company_name' => 'Customer Ltd']);
        $this->assertSame([$task->id => 'Prepare the offer'], LinkRecordAction::recent('task'), 'the newest records until you have opened some');

        $page = Livewire::test(Mailbox::class)->call('archive', 'INBOX', [1]);
        $this->assertTrue($page->effects['returns'][0] ?? null, 'the frame is told to refresh its list');

        $page->assertActionMounted('archivedDialog')
            ->assertMountedActionModalSee(['Message archived in Epesi and moved to CRM Archive folder', 'Also link to', 'Add another record'])
            ->fillForm(['links' => [
                ['type' => 'task', 'record' => $task->id],
                ['type' => 'company', 'record' => $company->id],
                ['type' => null, 'record' => null],
            ]])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('Linked to Task: Prepare the offer, Company: Customer Ltd');

        $mail = Mail::where('message_id', 'clicked@x')->sole();
        $this->assertSame($account->id, $mail->account_id);
        $this->assertTrue($mail->linkedRecords()->contains(fn ($record): bool => $record->is($task)));
        $this->assertTrue($mail->linkedRecords()->contains(fn ($record): bool => $record->is($company)), 'more than one record, in one go');
        $this->assertFalse(Mail::where('message_id', 'not-clicked@x')->exists(), 'nothing else in the folder is archived');
        $this->assertSame([2, 3], array_keys($server->messages['INBOX']), 'archived first, then moved off the inbox');
        $this->assertCount(1, $server->messages['CRM Archive'], 'kept on the mail server, in the archive folder');

        // Every time: there is no "Don't show this message again".
        Livewire::test(Mailbox::class)
            ->call('archive', 'INBOX', [2])
            ->assertActionMounted('archivedDialog')
            ->assertMountedActionModalDontSee(['Don&#039;t show this message again'])
            ->callMountedAction()
            ->call('archive', 'INBOX', [3])
            ->assertActionMounted('archivedDialog');

        Livewire::test(Mailbox::class)
            ->call('archive', 'INBOX', [99])
            ->assertActionNotMounted('archivedDialog')
            ->assertNotified('The message was not archived');
    }

    /**
     * A message a colleague archived (or an .eml upload): the archive keeps
     * its one copy, links it to whoever clicks, moves it like any other and
     * says so in the dialog, with linking as usual.
     */
    public function test_archiving_a_message_already_archived_links_it_to_you_moves_it_and_says_so(): void
    {
        $this->pretendInstalled();
        $account = $this->account();
        $own = Contact::create(['first_name' => 'Eli', 'last_name' => 'Employee']);
        $own->forceFill(['user_id' => $this->user->id])->save();
        $server = new FakeMailbox([
            'INBOX' => [
                1 => Eml::make(messageId: 'budget@x'),
                2 => Eml::make(messageId: 'new@x'),
            ],
            'CRM Archive' => [],
        ]);
        $this->app->instance(MailboxFactory::class, new class($server) extends MailboxFactory
        {
            public function __construct(private FakeMailbox $server) {}

            public function make(MailAccount $account): FakeMailbox
            {
                return $this->server;
            }
        });
        $colleague = $this->userWithRole('employee');
        $existing = app(MailArchiver::class)->archive(Eml::make(messageId: 'budget@x'), $colleague);

        // The Archive button's check lets it through: it is archived, so no contact warning.
        $pdo = DB::connection()->getPdo();
        $this->assertSame(['<budget@x>'], epesi_archive_matcher::archived(['<budget@x>'], function (string $sql, array $params) use ($pdo): ?array {
            $statement = $pdo->prepare($sql);
            $statement->execute($params);

            return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
        }));
        $plugin = file_get_contents(base_path('modules/Epesi/Roundcube/roundcube-plugins/epesi_archive/epesi_archive.php'));
        $this->assertStringNotContainsString("'already_archived'", $plugin, 'no more warning-only answer');

        $task = Task::create(['title' => 'IT budget', 'permission' => RecordPermission::Public]);

        Livewire::test(Mailbox::class)
            ->call('archive', 'INBOX', [1])
            ->assertActionMounted('archivedDialog')
            ->assertMountedActionModalSee(['Message already archived', 'it is now linked to you too, and moved to CRM Archive folder', 'Also link to'])
            ->fillForm(['links' => [['type' => 'task', 'record' => $task->id]]])
            ->callMountedAction()
            ->assertNotified('Linked to Task: IT budget');

        $this->assertSame(1, Mail::where('message_id', 'budget@x')->count(), 'still one copy');
        $this->assertTrue($own->mails()->whereKey($existing->id)->exists(), 'a pointer for whoever archived it');
        $this->assertTrue($existing->linkedRecords()->contains(fn ($record): bool => $record->is($task)));
        $this->assertSame([2], array_keys($server->messages['INBOX']), 'moved off the inbox like a new one');
        $this->assertCount(1, $server->messages['CRM Archive']);

        // A new one and an archived one in one click.
        $server->messages['INBOX'][3] = Eml::make(messageId: 'budget@x');
        Livewire::test(Mailbox::class)
            ->call('archive', 'INBOX', [2, 3])
            ->assertActionMounted('archivedDialog')
            ->assertMountedActionModalSee(['2 messages archived', 'Message archived in Epesi and moved to CRM Archive folder', 'One of them was already archived']);
    }

    public function test_a_message_the_server_will_not_move_is_still_archived_and_said_so(): void
    {
        $this->pretendInstalled();
        $this->account();
        $server = new FakeMailbox(['INBOX' => [1 => Eml::make(messageId: 'stuck@x')]]);
        $server->moveFails = true;
        $this->app->instance(MailboxFactory::class, new class($server) extends MailboxFactory
        {
            public function __construct(private FakeMailbox $server) {}

            public function make(MailAccount $account): FakeMailbox
            {
                return $this->server;
            }
        });

        $task = Task::create(['title' => 'Chase the reply', 'permission' => RecordPermission::Public]);

        // The dialog still opens, with the warning, and still links.
        Livewire::test(Mailbox::class)
            ->call('archive', 'INBOX', [1])
            ->assertActionMounted('archivedDialog')
            ->assertMountedActionModalSee(['Message archived in Epesi, but not moved to CRM Archive folder', 'MOVE failed', 'still in your inbox', 'Also link to'])
            ->fillForm(['links' => [['type' => 'task', 'record' => $task->id]]])
            ->callMountedAction()
            ->assertNotified('Linked to Task: Chase the reply');

        $mail = Mail::where('message_id', 'stuck@x')->sole();
        $this->assertTrue($mail->linkedRecords()->contains(fn ($record): bool => $record->is($task)));
        $this->assertArrayHasKey(1, $server->messages['INBOX']);
    }

    public function test_a_sent_copy_saved_to_the_archive_folder_is_archived_by_message_id(): void
    {
        $this->pretendInstalled();
        $this->account();
        $server = new FakeMailbox(['CRM Archive' => [1 => Eml::make(messageId: 'sent@x'), 2 => Eml::make(messageId: 'other@x')]]);
        $this->app->instance(MailboxFactory::class, new class($server) extends MailboxFactory
        {
            public function __construct(private FakeMailbox $server) {}

            public function make(MailAccount $account): FakeMailbox
            {
                return $this->server;
            }
        });

        $task = Task::create(['title' => 'Prepare the offer', 'permission' => RecordPermission::Public]);
        $company = Company::create(['company_name' => 'Customer Ltd']);

        // The Archive button's dialog, worded for a copy saved there, not moved.
        Livewire::test(Mailbox::class)
            ->call('archived', ['<sent@x>'])
            ->assertActionMounted('archivedDialog')
            ->assertMountedActionModalSee(['Message sent and archived', 'Sent message archived in Epesi and saved to CRM Archive folder', 'Also link to', 'Add another record'])
            ->assertMountedActionModalDontSee(['moved to'])
            ->fillForm(['links' => [
                ['type' => 'task', 'record' => $task->id],
                ['type' => 'company', 'record' => $company->id],
            ]])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('Linked to Task: Prepare the offer, Company: Customer Ltd');

        $this->assertSame(['sent@x'], Mail::pluck('message_id')->all());
        $mail = Mail::sole();
        $this->assertTrue($mail->linkedRecords()->contains(fn ($record): bool => $record->is($task)));
        $this->assertTrue($mail->linkedRecords()->contains(fn ($record): bool => $record->is($company)));
        $this->assertCount(2, $server->messages['CRM Archive'], 'a sent copy stays where Roundcube saved it');
    }

    /**
     * The Archive button warns when epesi_archive_matcher finds nothing; the
     * linking afterwards is ContactMatcher + MailArchiver's. They must agree.
     */
    public function test_the_archive_check_agrees_with_what_archiving_links(): void
    {
        $own = Contact::create(['first_name' => 'Eli', 'last_name' => 'Employee']);
        $own->forceFill(['user_id' => $this->user->id])->save();
        $own->syncCollection('emails', [['kind' => 'work', 'value' => 'me@ourcompany.test'], ['kind' => 'other', 'value' => 'eli@home.test']]);
        $this->user->refresh();

        $ann = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer']);
        $ann->syncCollection('emails', [['kind' => 'work', 'value' => 'ann@customer.test'], ['kind' => 'other', 'value' => 'ann.private@customer.test']]);
        $customer = Company::create(['company_name' => 'Customer Ltd']);
        $customer->syncCollection('emails', [['kind' => 'work', 'value' => 'sales@customer.test']]);

        $secret = Contact::create(['first_name' => 'Sam', 'last_name' => 'Secret', 'permission' => 2]);
        $secret->forceFill(['created_by' => $this->userWithRole('employee')->id])->save();
        $secret->syncCollection('emails', [['kind' => 'other', 'value' => 'sam@elsewhere.test']]);

        $gone = Contact::create(['first_name' => 'Gone', 'last_name' => 'Away']);
        $gone->syncCollection('emails', [['kind' => 'work', 'value' => 'gone@customer.test'], ['kind' => 'other', 'value' => 'gone.too@customer.test']]);
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

    /**
     * Roundcube fetches no Message-ID for a message list unless asked, and
     * calls each message "mid:<position in the folder>" instead. The check
     * compared those: an imported mail saved as "mid:4" made the 4th message
     * of any folder "already archived".
     */
    public function test_the_archive_check_asks_for_message_ids_and_ignores_roundcubes_placeholders(): void
    {
        $this->assertSame('MESSAGE-ID', epesi_archive_matcher::fetch_headers(null));
        $this->assertSame('X-SPAM MESSAGE-ID', epesi_archive_matcher::fetch_headers('x-spam  MESSAGE-ID'));

        $plugin = file_get_contents(base_path('modules/Epesi/Roundcube/roundcube-plugins/epesi_archive/epesi_archive.php'));
        $this->assertStringContainsString("add_hook('storage_init'", $plugin);

        $mail = app(MailArchiver::class)->archive(Eml::make(messageId: 'offer@customer.test'), $this->userWithRole('employee'));
        $mail->forceFill(['message_id' => 'mid:4'])->save();

        $pdo = DB::connection()->getPdo();
        $selectOne = function (string $sql, array $params) use ($pdo): ?array {
            $statement = $pdo->prepare($sql);
            $statement->execute($params);

            return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
        };

        $this->assertSame([], epesi_archive_matcher::archived(['mid:4'], $selectOne));

        $migration = require base_path('modules/Epesi/Mail/database/migrations/2026_10_08_160000_clear_placeholder_message_ids_on_epesi_mails.php');
        $migration->up();
        $this->assertNull($mail->fresh()->message_id);
    }

    /**
     * The check reads headers the way the plugin does, through Roundcube's own
     * rcube_imap::fetch_headers() and its real FETCH command and parser, from
     * a server that answers only the header fields asked for. Roundcube is
     * downloaded, never committed, so this needs `roundcube:install`.
     */
    public function test_roundcubes_header_fetch_gives_the_check_the_real_message_id(): void
    {
        $lib = base_path('storage/roundcube/program/lib/Roundcube');

        if (! is_file("$lib/rcube_imap_generic.php")) {
            $this->markTestSkipped('Roundcube is not installed (php artisan roundcube:install).');
        }

        defined('RCUBE_CHARSET') || define('RCUBE_CHARSET', 'UTF-8');
        spl_autoload_register(function (string $class) use ($lib): void {
            if (str_starts_with($class, 'rcube') && is_file("$lib/$class.php")) {
                require_once "$lib/$class.php";
            }
        });
        require_once base_path('modules/Epesi/Roundcube/roundcube-plugins/epesi_archive/epesi_archive.php');

        $fetch = function (string $fetchHeaders): \rcube_message_header {
            // A server that has message 4 (UID 19854) in INBOX.
            $server = new class extends \rcube_imap_generic
            {
                public function __construct()
                {
                    $this->fp = fopen('php://memory', 'w+');
                    $this->logged = true;
                    $this->selected = 'INBOX';
                    $this->prefs = ['timeout' => 0];
                }

                protected function putLine($string, $endln = true, $anonymized = false)
                {
                    preg_match('/^(A\d+) UID FETCH 19854 .*HEADER\.FIELDS \(([^)]*)\)/', $string, $m);
                    $all = [
                        'DATE' => 'Date: Wed, 12 Mar 2025 20:39:04 +0100',
                        'FROM' => 'From: Janusz Tylek <j@epe.si>',
                        'TO' => 'To: Joe Marcozzi <joe@vikingimporting.com>',
                        'SUBJECT' => 'Subject: IT Budget for 2025',
                        'MESSAGE-ID' => 'Message-ID: <23e398dd-461c-bd96-5d5e-75a1ebf1c2a9@epe.si>',
                    ];
                    $asked = explode(' ', $m[2]);
                    $headers = implode("\r\n", array_intersect_key($all, array_flip($asked)))."\r\n\r\n";

                    fwrite($this->fp, '* 4 FETCH (UID 19854 RFC822.SIZE 2048 FLAGS (\Seen) INTERNALDATE "12-Mar-2025 20:39:04 +0100" '
                        ."BODY[HEADER.FIELDS ({$m[2]})] {".strlen($headers)."}\r\n{$headers})\r\n{$m[1]} OK Fetch completed\r\n");
                    rewind($this->fp);

                    return true;
                }
            };

            $storage = (new \ReflectionClass(\rcube_imap::class))->newInstanceWithoutConstructor();
            $storage->conn = $server;
            $storage->set_options(['fetch_headers' => $fetchHeaders]);

            return $storage->fetch_headers('INBOX', [19854], false)[19854];
        };

        // Without the plugin's storage_init, Roundcube's placeholder: the bug.
        $this->assertSame('mid:4', $fetch('')->messageID);

        $plugin = (new \ReflectionClass(\epesi_archive::class))->newInstanceWithoutConstructor();
        $header = $fetch($plugin->storage_init(['fetch_headers' => ''])['fetch_headers']);

        $this->assertSame('IT Budget for 2025', $header->subject);
        $this->assertSame('<23e398dd-461c-bd96-5d5e-75a1ebf1c2a9@epe.si>', $header->messageID);

        // An imported mail stored as "mid:4" no longer makes it a duplicate; the real one does.
        $mail = app(MailArchiver::class)->archive(Eml::make(messageId: 'offer@customer.test'), $this->userWithRole('employee'));
        $mail->forceFill(['message_id' => 'mid:4'])->save();
        $pdo = DB::connection()->getPdo();
        $selectOne = function (string $sql, array $params) use ($pdo): ?array {
            $statement = $pdo->prepare($sql);
            $statement->execute($params);

            return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
        };

        $this->assertSame([], epesi_archive_matcher::archived([$header->messageID], $selectOne));

        $mail->forceFill(['message_id' => '23e398dd-461c-bd96-5d5e-75a1ebf1c2a9@epe.si'])->save();
        $this->assertSame([$header->messageID], epesi_archive_matcher::archived([$header->messageID], $selectOne));
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
