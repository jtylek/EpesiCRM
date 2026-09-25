<?php

namespace Tests\Feature\Modules;

use App\Models\StoredFile;
use App\Models\StoredFileContent;
use App\Models\User;
use App\Services\FileStorage;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ListContacts;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ViewContact;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\Mail\Filament\Actions\ComposeAction;
use Epesi\Modules\Mail\Filament\RelationManagers\MailAddressesRelationManager;
use Epesi\Modules\Mail\Filament\RelationManagers\MailsRelationManager;
use Epesi\Modules\Mail\Filament\Resources\MailAccounts\MailAccountResource;
use Epesi\Modules\Mail\Filament\Resources\Mails\MailResource;
use Epesi\Modules\Mail\Filament\Resources\Mails\Pages\ComposeMail;
use Epesi\Modules\Mail\Filament\Resources\Mails\Pages\ListMails;
use Epesi\Modules\Mail\Filament\Resources\Mails\Pages\ViewMail;
use Epesi\Modules\Mail\Filament\Widgets\UnreadMailWidget;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Models\MailAddress;
use Epesi\Modules\Mail\Services\Imap\MailboxFactory;
use Epesi\Modules\Mail\Services\MailArchiver;
use Epesi\Modules\Mail\Services\MailFetcher;
use Epesi\Modules\Mail\Services\MailSender;
use Epesi\Modules\Mail\Services\SmtpTransportFactory;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\Fakes\Eml;
use Tests\Fakes\FakeMailbox;
use Tests\Fakes\RecordingTransport;
use Tests\TestCase;

class MailTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected User $user;

    protected Contact $ann;

    protected Company $customer;

    protected RecordingTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = $this->userWithRole('employee', ['name' => 'Eli', 'email' => 'eli@ourcompany.test']);
        $this->actingAs($this->user);

        Contact::create(['first_name' => 'Eli', 'last_name' => 'Employee', 'email' => 'me@ourcompany.test'])
            ->forceFill(['user_id' => $this->user->id])->save();
        $this->user->refresh();

        $this->ann = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer', 'email' => 'ann@customer.test']);
        $this->customer = Company::create(['company_name' => 'Customer Ltd']);
        MailAddress::create([
            'addressable_type' => 'company',
            'addressable_id' => $this->customer->id,
            'email' => 'Sales@Customer.test',
        ]);

        $this->transport = new RecordingTransport;
        $this->app->instance(SmtpTransportFactory::class, new class($this->transport) extends SmtpTransportFactory
        {
            public function __construct(private RecordingTransport $transport) {}

            public function forAccount(MailAccount $account): RecordingTransport
            {
                return $this->transport;
            }
        });
    }

    protected function account(array $attributes = []): MailAccount
    {
        return MailAccount::create([
            'user_id' => $this->user->id,
            'name' => 'Work',
            'email' => 'me@ourcompany.test',
            'imap_host' => 'imap.test',
            'imap_login' => 'me',
            'imap_password' => 'secret',
            'smtp_host' => 'smtp.test',
            ...$attributes,
        ]);
    }

    public function test_archiving_links_contacts_and_companies_and_stores_parts(): void
    {
        $mail = app(MailArchiver::class)->archive(
            Eml::make(cc: 'sales@customer.test'),
            $this->user,
        );

        $this->assertSame('msg-1@customer.test', $mail->message_id);
        $this->assertSame('Offer', $mail->subject);
        $this->assertSame(Mail::INCOMING, $mail->direction);
        $this->assertSame('2026-09-22 08:15', $mail->date->utc()->format('Y-m-d H:i'));
        $this->assertSame($this->user->contact->id, $mail->employee_id);

        $linked = $mail->linkedRecords()->map(fn ($m) => $m->getMorphClass().':'.$m->getKey())->sort()->values()->all();
        $expected = collect(['company:'.$this->customer->id, 'contact:'.$this->ann->id, 'contact:'.$this->user->contact->id])->sort()->values()->all();
        $this->assertSame($expected, $linked,
            'Ann by her own address, the company by its extra one, and whoever archived it');

        $this->assertCount(2, $mail->attachments);
        $logo = $mail->attachments->firstWhere('name', 'logo.png');
        $this->assertTrue($logo->inline);
        $this->assertFalse($mail->attachments->firstWhere('name', 'offer.pdf')->inline);
        $this->assertStringContainsString($logo->url(inline: true), $mail->displayHtml());

        $this->assertSame(1, $mail->thread->message_count);
    }

    public function test_the_same_attachment_on_many_messages_is_stored_once(): void
    {
        $archiver = app(MailArchiver::class);
        $first = $archiver->archive(Eml::make(messageId: 'first@customer.test'), $this->user);
        $second = $archiver->archive(Eml::make(messageId: 'second@customer.test', subject: 'Offer again'), $this->user);

        // Two attachments on each message, two files on disk.
        $this->assertSame(4, StoredFile::count());
        $this->assertSame(2, StoredFileContent::count());
        $this->assertCount(2, Storage::disk(FileStorage::DISK)->allFiles());
        $this->assertSame(
            $first->attachments->firstWhere('name', 'offer.pdf')->storedFile->content_id,
            $second->attachments->firstWhere('name', 'offer.pdf')->storedFile->content_id,
        );

        // A note carrying the same PDF shares it too.
        app(FileStorage::class)->put('%PDF-1.4 fake offer', 'offer from the mail.pdf');
        $this->assertSame(2, StoredFileContent::count());

        $first->forceDelete();
        $this->assertSame('%PDF-1.4 fake offer', $second->attachments->firstWhere('name', 'offer.pdf')->storedFile->read());

        $second->forceDelete();
        $this->assertSame(1, StoredFile::count(), "only the note's");
        $this->assertCount(1, Storage::disk(FileStorage::DISK)->allFiles());
    }

    public function test_the_same_message_is_archived_once(): void
    {
        $first = app(MailArchiver::class)->archive(Eml::make(), $this->user);
        $again = app(MailArchiver::class)->archive(Eml::make(), $this->user, links: [$this->customer]);

        $this->assertTrue($first->is($again));
        $this->assertSame(1, Mail::count());
        $this->assertTrue($this->customer->mails()->whereKey($first->id)->exists(), 'a re-archive may still add links');
    }

    public function test_whoever_archives_is_linked_even_from_a_shared_mailbox(): void
    {
        $mail = app(MailArchiver::class)->archive(
            Eml::make(from: 'stranger@nowhere.test', to: 'support@ourcompany.test'),
            $this->user,
        );

        $this->assertTrue($this->user->contact->mails()->whereKey($mail->id)->exists(),
            "none of the addresses is Eli's, but Eli archived it");
    }

    public function test_a_message_is_archived_once_whoever_archives_it(): void
    {
        $colleague = $this->userWithRole('employee');
        Contact::create(['first_name' => 'Col', 'last_name' => 'League'])->forceFill(['user_id' => $colleague->id])->save();

        $first = app(MailArchiver::class)->archive(Eml::make(), $this->user);
        $again = app(MailArchiver::class)->archive(Eml::make(), $colleague->refresh());

        $this->assertTrue($first->is($again));
        $this->assertSame(1, Mail::count());
        $this->assertSame($this->user->contact->id, $again->employee_id, 'still the first archiver');
        $this->assertFalse($colleague->contact->mails()->exists(), 'the colleague archived nothing');

        $first->delete();
        $restored = app(MailArchiver::class)->archive(Eml::make(), $colleague);

        $this->assertTrue($restored->is($first), 'a deleted copy comes back instead of a second one');
        $this->assertFalse($restored->trashed());
        $this->assertSame(1, Mail::withTrashed()->count());
    }

    public function test_a_message_without_a_message_id_is_archived_once(): void
    {
        $raw = preg_replace('/^Message-ID: .*\r\n/m', '', Eml::make());

        $first = app(MailArchiver::class)->archive($raw, $this->user);
        $again = app(MailArchiver::class)->archive($raw, $this->user);

        $this->assertNull($first->message_id);
        $this->assertTrue($first->is($again));
        $this->assertSame(1, Mail::count());
    }

    public function test_replies_join_the_thread_even_out_of_order(): void
    {
        $archiver = app(MailArchiver::class);

        $reply = $archiver->archive(Eml::make(subject: 'Re: Offer', messageId: 'msg-2@us', inReplyTo: 'msg-1@customer.test'), $this->user);
        $original = $archiver->archive(Eml::make(), $this->user);

        $this->assertSame($reply->fresh()->thread_id, $original->thread_id);
        $this->assertSame(2, $original->thread->message_count);
        $this->assertSame('Offer', $original->thread->subject);
    }

    public function test_auto_archive_skips_mail_that_involves_no_known_record(): void
    {
        $skipped = app(MailArchiver::class)->archive(
            Eml::make(from: 'stranger@nowhere.test', messageId: 'spam@x'),
            $this->user,
            onlyIfMatched: true,
        );

        $this->assertNull($skipped);
        $this->assertSame(0, Mail::count());
    }

    public function test_fetching_archives_the_archive_folder_and_matched_new_inbox_mail(): void
    {
        $account = $this->account(['auto_archive' => true, 'auto_archive_folders' => ['INBOX']]);

        $server = new FakeMailbox([
            'CRM Archive' => [
                1 => Eml::make(from: 'stranger@nowhere.test', messageId: 'filed@x'),
            ],
            'INBOX' => [
                10 => Eml::make(messageId: 'old@customer.test'),
            ],
        ]);
        $this->app->instance(MailboxFactory::class, new class($server) extends MailboxFactory
        {
            public function __construct(private FakeMailbox $server) {}

            public function make(MailAccount $account): FakeMailbox
            {
                return $this->server;
            }
        });

        $fetcher = app(MailFetcher::class);

        $this->assertSame(1, $fetcher->fetch($account), 'everything filed in the archive folder; INBOX history is not imported');
        $this->assertTrue(Mail::where('message_id', 'filed@x')->exists());

        $server->messages['INBOX'][11] = Eml::make(messageId: 'new@customer.test');
        $server->messages['INBOX'][12] = Eml::make(from: 'stranger@nowhere.test', messageId: 'newsletter@x');

        $this->assertSame(1, $fetcher->fetch($account), 'only the new INBOX message from a known contact');
        $this->assertTrue(Mail::where('message_id', 'new@customer.test')->exists());
        $this->assertFalse(Mail::where('message_id', 'newsletter@x')->exists());
        $this->assertSame(0, $fetcher->fetch($account), 'nothing is read twice');

        $this->assertNotNull($account->fresh()->last_fetched_at);
    }

    public function test_an_empty_imap_login_means_the_e_mail_address(): void
    {
        $account = $this->account(['imap_login' => null]);
        $this->fakeServer(new FakeMailbox(['CRM Archive' => [1 => Eml::make()]]));

        $this->assertSame('me@ourcompany.test', $account->imapLogin());
        $this->assertSame(1, app(MailFetcher::class)->fetch($account), 'the account is fetched, not skipped');
    }

    public function test_sending_archives_the_sent_copy_without_bcc_and_threads_replies(): void
    {
        $account = $this->account();
        $incoming = app(MailArchiver::class)->archive(Eml::make(), $this->user);

        $sent = app(MailSender::class)->send(
            account: $account,
            user: $this->user,
            to: ['ann@customer.test'],
            subject: 'Re: Offer',
            html: '<p>Attached.</p>',
            bcc: ['boss@ourcompany.test'],
            inReplyTo: $incoming,
            archive: true,
        );

        $this->assertCount(1, $this->transport->sent);
        $this->assertSame(Mail::OUTGOING, $sent->direction);
        $this->assertSame($incoming->thread_id, $sent->thread_id);
        $this->assertStringNotContainsStringIgnoringCase('boss@ourcompany.test', (string) $sent->headers);
        $this->assertStringNotContainsStringIgnoringCase('boss@ourcompany.test', (string) $sent->to.$sent->cc);
        $this->assertTrue($this->ann->mails()->whereKey($sent->id)->exists());

        $envelope = $this->transport->sent[0]->getEnvelope()->getRecipients();
        $this->assertContains('boss@ourcompany.test', array_map(fn ($a) => $a->getAddress(), $envelope), 'Bcc still receives it');
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

    public function test_a_sent_message_is_copied_into_the_imap_sent_folder(): void
    {
        $server = $this->fakeServer(new FakeMailbox);
        $sender = app(MailSender::class);

        $archived = $sender->send($this->account(), $this->user, ['ann@customer.test'], 'Hello', '<p>Hi</p>',
            bcc: ['boss@ourcompany.test'], archive: true);

        $this->assertCount(1, $server->appended['Sent'] ?? []);
        $this->assertStringContainsString('Subject: Hello', $server->appended['Sent'][0]);
        $this->assertStringContainsString('Bcc: boss@ourcompany.test', $server->appended['Sent'][0], 'the sender\'s own copy shows the Bcc');
        $this->assertStringNotContainsString('boss@ourcompany.test', (string) $archived->headers.$archived->to.$archived->cc, 'the CRM archive never does');
        $this->assertSame([], $sender->warnings());

        $sender->send($this->account(['save_to_sent' => false]), $this->user, ['ann@customer.test'], 'Again', '<p>Hi</p>');
        $this->assertCount(1, $server->appended['Sent'], 'switched off per account');
    }

    public function test_a_failed_sent_copy_is_a_warning_not_a_failed_send(): void
    {
        $server = $this->fakeServer(new FakeMailbox);
        $server->failing = true;
        $sender = app(MailSender::class);

        $archived = $sender->send($this->account(), $this->user, ['ann@customer.test'], 'Hello', '<p>Hi</p>', archive: true);

        $this->assertCount(1, $this->transport->sent);
        $this->assertNotNull($archived);
        $this->assertStringContainsString('no copy was saved in "Sent"', $sender->warnings()[0]);
    }

    public function test_the_contact_page_has_e_mail_tabs_and_compose_sends(): void
    {
        $this->account();
        app(MailArchiver::class)->archive(Eml::make(), $this->user);

        $this->get(ViewContact::getUrl(['record' => $this->ann]))
            ->assertOk()
            ->assertSee('E-mails')
            ->assertSee('E-mail addresses');

        Livewire::test(MailsRelationManager::class, ['ownerRecord' => $this->ann, 'pageClass' => ViewContact::class])
            ->assertOk()
            ->assertSee('Offer')
            ->assertActionHasUrl(TestAction::make('composeNew')->table(), MailResource::getUrl('compose', ['about' => 'contact:'.$this->ann->getKey()]));

        Livewire::withQueryParams(['about' => 'contact:'.$this->ann->getKey()])
            ->test(ComposeMail::class)
            ->assertFormSet(['to' => ['ann@customer.test']])
            ->fillForm(['subject' => 'Hello Ann', 'body' => '<p>Hi</p>'])
            ->call('send')
            ->assertHasNoFormErrors()
            ->assertRedirect(ViewContact::getUrl(['record' => $this->ann]));

        $this->assertCount(1, $this->transport->sent);
        $this->assertSame(['ann@customer.test'], array_map(
            fn ($a) => $a->getAddress(),
            $this->transport->sent[0]->getOriginalMessage()->getTo(),
        ), 'compose on a contact starts with their address');
        $this->assertTrue($this->ann->mails()->where('subject', 'Hello Ann')->exists());
    }

    public function test_an_address_in_a_list_is_cut_short_and_mails_to_without_an_account(): void
    {
        Contact::create(['first_name' => 'Husam', 'last_name' => 'Aljarmozi', 'email' => 'husam.aljarmozi@globaladvocates.net']);

        Livewire::test(ListContacts::class)
            ->assertSeeHtml('href="mailto:ann@customer.test"')
            ->assertSeeHtml('href="mailto:husam.aljarmozi@globaladvocates.net"')
            ->assertSee('husam.aljarmozi@globaladv…');

        $this->get(ViewContact::getUrl(['record' => $this->ann]))
            ->assertOk()
            ->assertSee('href="mailto:ann@customer.test"', escape: false);
    }

    public function test_an_address_in_a_list_opens_compose_for_it_with_an_account(): void
    {
        $this->account();

        $compose = 'href="'.e(ComposeAction::url($this->ann, to: 'ann@customer.test')).'"';
        Livewire::test(ListContacts::class)->assertSeeHtml($compose);
        $this->get(ViewContact::getUrl(['record' => $this->ann]))->assertOk()->assertSee($compose, escape: false);

        Livewire::withQueryParams(['about' => 'contact:'.$this->ann->getKey(), 'to' => 'ann.private@home.test'])
            ->test(ComposeMail::class)
            ->assertFormSet(['to' => ['ann.private@home.test']]);

        Livewire::withQueryParams(['about' => 'contact:'.$this->ann->getKey(), 'to' => 'not an address'])
            ->test(ComposeMail::class)
            ->assertFormSet(['to' => ['ann@customer.test']]);
    }

    public function test_adding_an_address_files_existing_mail_under_the_record(): void
    {
        app(MailArchiver::class)->archive(Eml::make(from: 'ann.private@home.test', messageId: 'home@x'), $this->user);
        $this->assertSame(0, $this->ann->mails()->count());

        Livewire::test(MailAddressesRelationManager::class, ['ownerRecord' => $this->ann, 'pageClass' => ViewContact::class])
            ->callAction(TestAction::make('create')->table(), data: ['email' => 'ann.private@home.test'])
            ->assertHasNoActionErrors();

        $this->assertSame(1, $this->ann->mails()->count());
    }

    public function test_the_message_page_renders_the_body_sandboxed_and_serves_attachments(): void
    {
        $this->account();
        $mail = app(MailArchiver::class)->archive(Eml::make(html: '<p>Hi</p><script>alert(1)</script>'), $this->user);
        $pdf = $mail->attachments->firstWhere('name', 'offer.pdf');
        $logo = $mail->attachments->firstWhere('name', 'logo.png');

        $this->get(MailResource::getUrl('view', ['record' => $mail]))
            ->assertOk()
            ->assertSee('sandbox="allow-popups allow-popups-to-escape-sandbox"', false)
            ->assertSee('offer.pdf')
            ->assertDontSee('<script>alert(1)</script>', false);

        $this->get($pdf->url())->assertOk()->assertHeader('Content-Type', 'application/octet-stream')->assertDownload('offer.pdf');
        $this->get($logo->url(inline: true))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('epesi.mail.attachment', ['mail' => $mail->id, 'attachment' => $pdf->id, 'inline' => 1]))
            ->assertHeader('Content-Type', 'application/octet-stream');

        Livewire::test(ViewMail::class, ['record' => $mail->getKey()])
            ->assertActionVisible('composeReply')
            ->assertActionHasUrl('composeReply', MailResource::getUrl('compose', ['mode' => 'reply', 'source' => $mail->getKey()]))
            ->callAction('linkRecord', data: ['type' => 'company', 'record' => $this->customer->id]);
        $this->assertTrue($this->customer->mails()->whereKey($mail->id)->exists());

        auth()->logout();
        $this->get($pdf->url())->assertForbidden();
        $this->get($logo->url(inline: true))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('epesi.mail.attachment', ['mail' => $mail->id, 'attachment' => $logo->id, 'inline' => 1]))
            ->assertForbidden();
    }

    public function test_mail_accounts_are_private_to_their_owner_and_never_echo_passwords(): void
    {
        $mine = $this->account();
        $other = $this->userWithRole('super_admin');
        $theirs = MailAccount::create(['user_id' => $other->id, 'name' => 'Boss', 'email' => 'boss@ourcompany.test']);

        $this->assertNotSame('secret', $mine->getRawOriginal('imap_password'), 'encrypted at rest');

        $this->get(MailAccountResource::getUrl('index', panel: 'user-settings'))->assertOk()->assertSee('Work')->assertDontSee('Boss');
        $this->get(MailAccountResource::getUrl('edit', ['record' => $mine], panel: 'user-settings'))->assertOk()->assertDontSee('secret');
        $this->get(MailAccountResource::getUrl('edit', ['record' => $theirs], panel: 'user-settings'))->assertNotFound();
    }

    public function test_the_dashboard_widget_shows_cached_unread_counts_and_recent_mail(): void
    {
        $this->assertFalse(UnreadMailWidget::canView(), 'hidden until the user has a mailbox');

        $server = $this->fakeServer(new FakeMailbox);
        $server->unseenCounts['INBOX'] = 3;
        $this->account();
        app(MailArchiver::class)->archive(Eml::make(), $this->user);

        $this->assertTrue(UnreadMailWidget::canView());
        $this->get('/')->assertOk()->assertSeeLivewire(UnreadMailWidget::class);

        Livewire::test(UnreadMailWidget::class)
            ->assertSee('3 unread')
            ->assertSee('Offer');

        $server->unseenCounts['INBOX'] = 5;
        Livewire::test(UnreadMailWidget::class)->assertSee('3 unread', false);

        Livewire::test(UnreadMailWidget::class)
            ->call('refreshCounts')
            ->assertSee('5 unread');

        $server->failing = true;
        Livewire::test(UnreadMailWidget::class)
            ->call('refreshCounts')
            ->assertSee('unavailable')
            ->assertSee('connection refused');
    }

    public function test_the_list_page_offers_compose_and_eml_upload(): void
    {
        $this->account();

        Livewire::test(ListMails::class)
            ->assertActionVisible('composeNew')
            ->assertActionVisible('uploadEml');
    }

    public function test_eml_upload_goes_by_extension_not_by_sniffed_mime_type(): void
    {
        // Postbox saves Subject: first; with no Received:/From header on top,
        // PHP's fileinfo guesses the HTML part's type instead of message/rfc822.
        // In tests Livewire reports the fake's own type instead of sniffing it.
        $eml = "Subject: Offer\r\n".Eml::make(html: '<html><body><p>test worked</p></body></html>');
        $sniffed = (new \finfo(FILEINFO_MIME_TYPE))->buffer($eml);
        $this->assertSame('text/html', $sniffed);

        Livewire::test(ListMails::class)
            ->callAction('uploadEml', data: ['files' => [UploadedFile::fake()->createWithContent('This is a test.eml', $eml)->mimeType($sniffed)]])
            ->assertHasNoActionErrors();

        $this->assertSame(1, Mail::count());

        Livewire::test(ListMails::class)
            ->callAction('uploadEml', data: ['files' => [UploadedFile::fake()->createWithContent('notes.txt', Eml::make(messageId: 'msg-2@customer.test'))]])
            ->assertHasActionErrors(['files']);

        $this->assertSame(1, Mail::count());
    }
}
