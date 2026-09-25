<?php

namespace Tests\Feature;

use App\Models\StoredFile;
use App\Models\StoredFileContent;
use App\Models\User;
use App\Services\LegacyImport\Importers\CompaniesImporter;
use App\Services\LegacyImport\Importers\ContactsImporter;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Models\MailAddress;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `import:legacy` against a hand-built slice of a legacy Epesi database (a
 * second in-memory SQLite connection standing in for MySQL) and data/
 * directory.
 */
class LegacyImportTest extends TestCase
{
    use RefreshDatabase;

    protected string $dataDir;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('legacy');

        $this->dataDir = storage_path('framework/testing/legacy-data-'.uniqid());
        File::ensureDirectoryExists($this->dataDir);
        config(['epesi-mail.legacy_data_dir' => $this->dataDir]);

        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dataDir);

        parent::tearDown();
    }

    protected function legacy(): Builder
    {
        return Schema::connection('legacy');
    }

    public function test_replayed_history_is_stored_under_the_morph_alias_so_it_shows(): void
    {
        $this->legacy()->create('company_data_1', function (Blueprint $t) {
            $t->integer('id');
            $t->dateTime('created_on');
            $t->integer('created_by')->nullable();
            $t->integer('active')->default(1);
            foreach (['company_name', 'short_name', 'phone', 'fax', 'email', 'web_address', 'memo', 'group', 'permission',
                'address_1', 'address_2', 'city', 'country', 'zone', 'postal_code', 'tax_id'] as $f) {
                $t->text("f_{$f}")->nullable();
            }
        });
        $this->legacy()->create('company_edit_history', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('company_id');
            $t->dateTime('edited_on');
            $t->integer('edited_by')->nullable();
        });
        $this->legacy()->create('company_edit_history_data', function (Blueprint $t) {
            $t->integer('edit_id');
            $t->string('field');
            $t->text('old_value')->nullable();
        });

        DB::connection('legacy')->table('company_data_1')->insert(['id' => 3, 'created_on' => '2020-01-01 10:00:00', 'f_company_name' => 'Acme Ltd', 'f_permission' => '0']);
        DB::connection('legacy')->table('company_edit_history')->insert(['id' => 1, 'company_id' => 3, 'edited_on' => '2021-01-01 10:00:00']);
        DB::connection('legacy')->table('company_edit_history_data')->insert(['edit_id' => 1, 'field' => 'company_name', 'old_value' => 'Acme']);

        activity()->disableLogging();
        (new CompaniesImporter)->run();
        activity()->enableLogging();

        $company = Company::withTrashed()->where('legacy_id', 3)->sole();
        $this->assertSame(['created', 'updated'], $company->activities()->orderBy('created_at')->pluck('event')->all());
    }

    public function test_a_company_email_an_earlier_company_already_has_is_left_blank_and_reported(): void
    {
        $this->legacyRecordTable('company_data_1');
        DB::connection('legacy')->table('company_data_1')->insert([
            ['id' => 1, 'created_on' => '2020-01-01 10:00:00', 'f_company_name' => 'Acme', 'f_email' => 'info@acme.test', 'active' => 1],
            ['id' => 2, 'created_on' => '2020-01-02 10:00:00', 'f_company_name' => 'Acme Poland', 'f_email' => 'info@acme.test ', 'active' => 1],
            ['id' => 3, 'created_on' => '2020-01-03 10:00:00', 'f_company_name' => 'Blank One', 'f_email' => ' ', 'active' => 1],
            ['id' => 4, 'created_on' => '2020-01-04 10:00:00', 'f_company_name' => 'Blank Two', 'f_email' => ' ', 'active' => 1],
            ['id' => 5, 'created_on' => '2020-01-05 10:00:00', 'f_company_name' => 'Other', 'f_email' => 'other@acme.test', 'active' => 0],
            ['id' => 6, 'created_on' => '2020-01-06 10:00:00', 'f_company_name' => 'Other Twin', 'f_email' => 'other@acme.test', 'active' => 1],
        ]);

        $emails = fn (): array => Company::withTrashed()->orderBy('legacy_id')->pluck('email', 'legacy_id')->all();
        $expected = [1 => 'info@acme.test', 2 => null, 3 => null, 4 => null, 5 => 'other@acme.test', 6 => null];

        activity()->disableLogging();
        $summary = (new CompaniesImporter)->run(withHistory: false);
        $this->assertSame($expected, $emails());
        $this->assertSame([
            'company#2: email "info@acme.test" is already used by company#1, left blank',
            'company#6: email "other@acme.test" is already used by company#5, left blank',
        ], $summary->warnings);

        // Re-running is idempotent: the same records keep the address.
        $again = (new CompaniesImporter)->run(withHistory: false);
        activity()->enableLogging();
        $this->assertSame($expected, $emails());
        $this->assertSame($summary->warnings, $again->warnings);
    }

    public function test_a_contact_email_an_earlier_contact_already_has_is_left_blank_and_reported(): void
    {
        $this->legacyRecordTable('contact_data_1');
        DB::connection('legacy')->table('contact_data_1')->insert([
            ['id' => 1, 'created_on' => '2020-01-01 10:00:00', 'f_last_name' => 'Buyer', 'f_first_name' => 'Ann', 'f_email' => 'ann@acme.test'],
            ['id' => 2, 'created_on' => '2020-01-02 10:00:00', 'f_last_name' => 'Buyer', 'f_first_name' => 'Anna', 'f_email' => 'ann@acme.test'],
            ['id' => 3, 'created_on' => '2020-01-03 10:00:00', 'f_last_name' => 'Nomail', 'f_first_name' => 'Ned', 'f_email' => ''],
        ]);

        activity()->disableLogging();
        $summary = (new ContactsImporter)->run(withHistory: false);
        activity()->enableLogging();

        $this->assertSame([1 => 'ann@acme.test', 2 => null, 3 => null], Contact::withTrashed()->orderBy('legacy_id')->pluck('email', 'legacy_id')->all());
        $this->assertSame(['contact#2: email "ann@acme.test" is already used by contact#1, left blank'], $summary->warnings);
    }

    public function test_mail_accounts_addresses_messages_threads_links_and_attachments_are_imported(): void
    {
        [$user, $ann, $acme, $task] = $this->coreRecords();
        $this->buildLegacyMail();

        $this->artisan('import:legacy', ['tab' => 'mail'])->assertSuccessful();

        // Accounts: host:port split, archive folder under the IMAP root,
        // encrypted password decrypted with the legacy key, plain one kept.
        $account = MailAccount::query()->where('user_id', $user->id)->sole();
        $this->assertSame(['imap.acme.test', 993, 'ssl'], [$account->imap_host, $account->imap_port, $account->imap_security]);
        $this->assertSame('INBOX.CRM Archive', $account->archive_folder);
        $this->assertSame('imap-secret', $account->imap_password);
        $this->assertSame('smtp-plain', $account->smtp_password);

        $this->assertTrue(MailAddress::query()->where('email', 'sales@acme.test')->where('addressable_id', $acme->id)->exists());

        // Messages, links (new-style, old-style and Related tokens) and threads.
        $offer = Mail::query()->where('legacy_id', 11)->sole();
        $reply = Mail::query()->where('legacy_id', 12)->sole();
        $this->assertSame('Offer', $offer->subject);
        $this->assertSame('boss@acme.test', $offer->cc);
        $this->assertSame(Mail::INCOMING, $offer->direction);
        $this->assertSame(Mail::OUTGOING, $reply->direction);
        $this->assertSame($offer->thread_id, $reply->thread_id);
        $this->assertSame(2, $offer->thread->message_count);

        // ...and the employee who archived it, as MailArchiver links whoever archives.
        $this->assertNotNull($offer->employee_id);
        $links = $offer->linkedRecords()->map(fn ($m) => $m->getMorphClass().':'.$m->getKey())->sort()->values()->all();
        $expected = collect(['company:'.$acme->id, 'contact:'.$ann->id, 'contact:'.$offer->employee_id, 'task:'.$task->id])->sort()->values()->all();
        $this->assertSame($expected, $links);

        // Attachments from FileStorage and from the old per-mail directory;
        // the inline image's Epesi URL turned back into a working cid.
        $this->assertSame(['logo.png', 'offer.pdf'], $offer->attachments->pluck('name')->sort()->values()->all());
        $this->assertSame('%PDF offer', $offer->attachments->firstWhere('name', 'offer.pdf')->storedFile->read());
        $logo = $offer->attachments->firstWhere('name', 'logo.png');
        $this->assertTrue($logo->inline);
        $this->assertStringContainsString('/mail/'.$offer->id.'/attachments/'.$logo->id, $offer->displayHtml());
        $this->assertStringNotContainsString('get.php', $offer->displayHtml());

        // Idempotent, down to the file storage: one copy of each file still.
        $this->artisan('import:legacy', ['tab' => 'mail'])->assertSuccessful();
        $this->assertSame(2, Mail::count());
        $this->assertSame(2, $offer->fresh()->attachments()->count());
        $this->assertSame(2, StoredFile::count());
        $this->assertSame(2, StoredFileContent::count());
        $this->assertSame(1, MailAccount::count());
    }

    /**
     * @return array{0: User, 1: Contact, 2: Company, 3: Task}
     */
    protected function coreRecords(): array
    {
        $user = User::factory()->create();
        $user->forceFill(['legacy_id' => 7])->save();

        $me = Contact::create(['first_name' => 'Eli', 'last_name' => 'Employee', 'email' => 'eli@ours.test']);
        $me->forceFill(['legacy_id' => 1, 'user_id' => $user->id])->save();
        $ann = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer', 'email' => 'ann@acme.test']);
        $ann->forceFill(['legacy_id' => 5])->save();
        $acme = Company::create(['company_name' => 'Acme']);
        $acme->forceFill(['legacy_id' => 3])->save();
        $task = Task::create(['title' => 'Send offer']);
        $task->forceFill(['legacy_id' => 9])->save();

        return [$user, $ann, $acme, $task];
    }

    /** A legacy `<tab>_data_1` table holding just the columns a test fills; the importers read the rest as null. */
    protected function legacyRecordTable(string $table): void
    {
        $this->legacy()->create($table, function (Blueprint $t) {
            $t->integer('id');
            $t->dateTime('created_on');
            $t->integer('created_by')->nullable();
            $t->integer('active')->default(1);
            foreach (['company_name', 'last_name', 'first_name', 'email'] as $f) {
                $t->text("f_{$f}")->nullable();
            }
        });
    }

    protected function buildLegacyMail(): void
    {
        $l = DB::connection('legacy');

        $this->legacy()->create('rc_accounts_data_1', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('active')->default(1);
            foreach (['epesi_user', 'email', 'account_name', 'server', 'login', 'password', 'security', 'smtp_server',
                'smtp_auth', 'smtp_login', 'smtp_password', 'smtp_security', 'default_account', 'archive_on_sending', 'imap_root'] as $f) {
                $t->text("f_{$f}")->nullable();
            }
        });
        $this->legacy()->create('rc_multiple_emails_data_1', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('active')->default(1);
            $t->text('f_record_type');
            $t->integer('f_record_id');
            $t->text('f_email');
        });
        $this->legacy()->create('rc_mails_data_1', function (Blueprint $t) {
            $t->integer('id');
            $t->dateTime('created_on');
            $t->integer('created_by')->nullable();
            $t->integer('active')->default(1);
            foreach (['subject', 'contacts', 'employee', 'related', 'date', 'headers_data', 'body', 'from', 'to', 'thread', 'message_id', 'references'] as $f) {
                $t->text("f_{$f}")->nullable();
            }
        });
        $this->legacy()->create('rc_mails_attachments', function (Blueprint $t) {
            $t->integer('mail_id');
            $t->text('type')->nullable();
            $t->text('name')->nullable();
            $t->text('mime_id')->nullable();
            $t->integer('attachment')->default(1);
            $t->integer('file_id')->nullable();
        });
        $this->legacy()->create('utils_filestorage_files', function (Blueprint $t) {
            $t->integer('id');
            $t->text('hash');
        });
        $this->legacy()->create('utils_filestorage', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('file_id');
        });

        // The legacy key file and one password encrypted exactly as
        // CRM_MailCommon::encrypt() did.
        $key = random_bytes(32);
        File::ensureDirectoryExists($this->dataDir.'/CRM_Mail');
        File::put($this->dataDir.'/CRM_Mail/encryption.key', $key);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt('imap-secret', 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        $l->table('rc_accounts_data_1')->insert([
            'id' => 1, 'f_epesi_user' => '7', 'f_email' => 'eli@ours.test', 'f_account_name' => 'Work',
            'f_server' => 'imap.acme.test:993', 'f_login' => 'eli', 'f_password' => base64_encode($iv.$tag.$cipher),
            'f_security' => 'ssl', 'f_smtp_server' => 'smtp.acme.test', 'f_smtp_auth' => '1',
            'f_smtp_password' => 'smtp-plain', 'f_smtp_security' => 'tls', 'f_default_account' => '1', 'f_imap_root' => 'INBOX.',
        ]);
        $l->table('rc_multiple_emails_data_1')->insert(['id' => 1, 'f_record_type' => 'company', 'f_record_id' => 3, 'f_email' => 'Sales@acme.test']);

        $mime = str_repeat('a', 32);
        $l->table('rc_mails_data_1')->insert([
            [
                'id' => 11, 'created_on' => '2025-03-01 09:00:00', 'created_by' => 7,
                'f_subject' => 'Offer', 'f_contacts' => '__contact/5__C:3__', 'f_employee' => '1', 'f_related' => '__task/9__',
                'f_date' => '2025-03-01 08:55:00', 'f_headers_data' => "date: Sat, 1 Mar 2025\ncc: boss@acme.test",
                'f_body' => '<p>Please see the offer.</p><img src="get.php?mail_id=__MAIL_ID__&amp;mime_id='.$mime.'">',
                'f_from' => 'Ann Buyer <ann@acme.test>', 'f_to' => 'eli@ours.test', 'f_thread' => '4',
                'f_message_id' => 'offer@acme.test', 'f_references' => null,
            ],
            [
                'id' => 12, 'created_on' => '2025-03-02 09:00:00', 'created_by' => 7,
                'f_subject' => 'Re: Offer', 'f_contacts' => '__5__', 'f_employee' => '1', 'f_related' => null,
                'f_date' => '2025-03-02 08:55:00', 'f_headers_data' => 'in_reply_to: <offer@acme.test>',
                'f_body' => '<pre>Thanks!</pre>', 'f_from' => 'Eli <eli@ours.test>', 'f_to' => 'ann@acme.test', 'f_thread' => '4',
                'f_message_id' => 'reply@ours.test', 'f_references' => '<offer@acme.test>',
            ],
        ]);

        // offer.pdf in Utils_FileStorage; logo.png still in the pre-2026-06
        // per-mail directory.
        $hash = hash('sha512', '%PDF offer');
        $dir = $this->dataDir.'/Utils_FileStorage/'.implode('/', str_split(substr($hash, 0, 5)));
        File::ensureDirectoryExists($dir);
        File::put($dir.'/'.substr($hash, 5), '%PDF offer');
        $l->table('utils_filestorage_files')->insert(['id' => 50, 'hash' => $hash]);
        $l->table('utils_filestorage')->insert(['id' => 60, 'file_id' => 50]);

        File::ensureDirectoryExists($this->dataDir.'/CRM_Mail/attachments/11');
        File::put($this->dataDir.'/CRM_Mail/attachments/11/'.$mime, "\x89PNG fake");

        $l->table('rc_mails_attachments')->insert([
            ['mail_id' => 11, 'type' => 'application/pdf', 'name' => 'offer.pdf', 'mime_id' => str_repeat('b', 32), 'attachment' => 1, 'file_id' => 60],
            ['mail_id' => 11, 'type' => 'image/png', 'name' => 'logo.png', 'mime_id' => $mime, 'attachment' => 0, 'file_id' => null],
        ]);
    }
}
