<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use Epesi\Modules\Mail\Filament\Resources\MailAccounts\Pages\CreateMailAccount;
use Epesi\Modules\Mail\Filament\Resources\MailAccounts\Pages\EditMailAccount;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\Imap\JunkFolder;
use Epesi\Modules\Mail\Services\Imap\MailboxFactory;
use epesi_sso_folders;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\Fakes\FakeMailbox;
use Tests\TestCase;

/** The mail account's Spam folder: where Mark as junk in the Mailbox moves messages. */
class MailJunkFolderTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->userWithRole('employee', ['name' => 'Eli', 'email' => 'eli@ourcompany.test']);
        $this->actingAs($this->user);
        Filament::setCurrentPanel('user-settings');
    }

    protected function fakeServer(FakeMailbox $server): FakeMailbox
    {
        $this->app->instance(MailboxFactory::class, new class($server) extends MailboxFactory
        {
            public function __construct(private FakeMailbox $server) {}

            public function make(MailAccount $account): FakeMailbox
            {
                return $this->server;
            }
        });

        return $server;
    }

    /** @return array<string, mixed> */
    protected function form(array $overrides = []): array
    {
        return [
            'name' => 'Work',
            'email' => 'me@ourcompany.test',
            'imap_host' => 'imap.test',
            'imap_password' => 'secret',
            'smtp_host' => 'smtp.test',
            ...$overrides,
        ];
    }

    public function test_the_server_flag_wins_then_names_then_nothing(): void
    {
        $this->assertSame('[Gmail]/Spam', JunkFolder::pick([
            'INBOX' => ['delimiter' => '/', 'flags' => ['\\HasNoChildren']],
            'Junk' => ['delimiter' => '/', 'flags' => []],
            '[Gmail]/Spam' => ['delimiter' => '/', 'flags' => ['\\HasNoChildren', '\\Junk']],
        ]), 'the \\Junk flag beats a folder merely named Junk');

        $this->assertSame('INBOX.spam', JunkFolder::pick([
            'INBOX' => ['delimiter' => '.', 'flags' => []],
            'INBOX.Clients.Junk' => ['delimiter' => '.', 'flags' => []],
            'INBOX.spam' => ['delimiter' => '.', 'flags' => []],
        ]));

        $this->assertNull(JunkFolder::pick(['INBOX' => ['delimiter' => '/', 'flags' => []], 'Spam reports' => ['delimiter' => '/', 'flags' => []]]));
    }

    public function test_epesi_and_roundcube_search_for_the_same_names(): void
    {
        require_once base_path('modules/Epesi/Roundcube/roundcube-plugins/epesi_sso/epesi_sso_folders.php');

        $this->assertSame(JunkFolder::NAMES, epesi_sso_folders::JUNK_NAMES);
    }

    public function test_a_new_account_gets_its_spam_folder_from_the_server(): void
    {
        $server = $this->fakeServer(new FakeMailbox(['INBOX' => [], '[Gmail]/Spam' => []]));
        $server->flags['[Gmail]/Spam'] = ['\\Junk'];

        Livewire::test(CreateMailAccount::class)->fillForm($this->form())->call('create')->assertHasNoFormErrors();

        $this->assertSame('[Gmail]/Spam', MailAccount::sole()->junk_folder);
    }

    public function test_without_one_it_says_so_and_a_typed_folder_is_kept(): void
    {
        $this->fakeServer(new FakeMailbox(['INBOX' => [], 'Sent' => []]));

        Livewire::test(CreateMailAccount::class)->fillForm($this->form())->call('create')
            ->assertNotified(__('No spam folder found'));
        $this->assertNull(MailAccount::sole()->junk_folder);

        Livewire::test(EditMailAccount::class, ['record' => MailAccount::sole()->getKey()])
            ->fillForm(['junk_folder' => 'Bulk'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Bulk', MailAccount::sole()->junk_folder, 'a typed folder is not searched over');
    }

    public function test_an_unreachable_server_leaves_it_empty_for_roundcube_to_find(): void
    {
        $server = $this->fakeServer(new FakeMailbox(['INBOX' => [], 'Spam' => []]));
        $server->failing = true;

        Livewire::test(CreateMailAccount::class)->fillForm($this->form())->call('create')->assertHasNoFormErrors();

        $this->assertNull(MailAccount::sole()->junk_folder);
    }

    public function test_save_cancel_and_delete_are_header_actions_not_a_row_under_the_form(): void
    {
        $this->fakeServer(new FakeMailbox(['INBOX' => []]));
        $account = MailAccount::create(['user_id' => $this->user->id, ...$this->form()]);

        Livewire::test(EditMailAccount::class, ['record' => $account->getKey()])
            ->assertActionExists('cancel')
            ->assertActionExists('save', fn ($action): bool => $action->getLabel() === 'Save changes')
            ->assertActionExists('delete', fn ($action): bool => $action->getLabel() === 'Delete account');

        Livewire::test(CreateMailAccount::class)->assertActionExists('create')->assertActionExists('cancel');
    }

    public function test_detect_fills_the_field_of_a_saved_account(): void
    {
        $server = $this->fakeServer(new FakeMailbox(['INBOX' => []]));
        $account = MailAccount::create(['user_id' => $this->user->id, ...$this->form()]);
        $server->messages['Spam'] = [];

        Livewire::test(EditMailAccount::class, ['record' => $account->getKey()])
            ->callAction(TestAction::make('detectJunkFolder')->schemaComponent('junk_folder'))
            ->assertSchemaStateSet(['junk_folder' => 'Spam']);

        // A hidden action can't be resolved, so look at the page: a new account has no server to ask yet.
        Livewire::test(EditMailAccount::class, ['record' => $account->getKey()])->assertSee('Autodetect Spam folder');
        Livewire::test(CreateMailAccount::class)->assertDontSee('Autodetect Spam folder');
    }
}
