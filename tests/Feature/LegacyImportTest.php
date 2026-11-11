<?php

namespace Tests\Feature;

use App\Models\LoginAudit;
use App\Models\StoredFile;
use App\Models\StoredFileContent;
use App\Models\User;
use App\Services\LegacyImport\Importer;
use App\Services\LegacyImport\ImporterRegistry;
use App\Services\LegacyImport\Importers\CompaniesImporter;
use App\Services\LegacyImport\Importers\ContactsImporter;
use App\Services\LegacyImport\LegacyFileTransfer;
use App\Services\LegacyImport\TimeBudget;
use App\Services\LegacyImport\TimeBudgetReached;
use Database\Seeders\RoleSeeder;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\Mail\LegacyImport\MailImporter;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\MigrationWizard\Files\FileLedger;
use Epesi\Modules\MigrationWizard\Files\MovingLegacyFileTransfer;
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

    public function test_rows_entered_here_block_the_import_unless_kept_with_keep_existing(): void
    {
        $this->legacyRecordTable('company_data_1');
        DB::connection('legacy')->table('company_data_1')->insert(['id' => 1, 'created_on' => '2020-01-01 10:00:00', 'f_company_name' => 'Acme', 'active' => 1]);
        $mine = Company::create(['company_name' => 'Entered here']);

        $this->artisan('import:legacy', ['tab' => 'companies', '--no-history' => true])
            ->expectsOutputToContain('Refusing to import: companies')
            ->assertFailed();
        $this->assertSame(0, Company::whereNotNull('legacy_id')->count());

        $this->artisan('import:legacy', ['tab' => 'companies', '--no-history' => true, '--keep-existing' => true])
            ->expectsOutputToContain('Keeping the rows with no legacy_id in companies')
            ->assertSuccessful();

        $imported = Company::where('legacy_id', 1)->sole();
        $this->assertSame('Acme', $imported->company_name);
        $this->assertNotSame($mine->id, $imported->id);
        $this->assertSame('Entered here', $mine->fresh()->company_name);
    }

    public function test_a_company_email_an_earlier_company_already_has_is_skipped_and_reported(): void
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

        $emails = fn (): array => Company::withTrashed()->orderBy('legacy_id')->get()
            ->mapWithKeys(fn (Company $c): array => [$c->legacy_id => $c->emails->first()?->value])->all();
        // #5 is deleted, so its live twin #6 takes the address over.
        $expected = [1 => 'info@acme.test', 2 => null, 3 => null, 4 => null, 5 => null, 6 => 'other@acme.test'];

        activity()->disableLogging();
        $summary = (new CompaniesImporter)->run(withHistory: false);
        $this->assertSame($expected, $emails());
        $this->assertSame([
            'company#2: e-mail address "info@acme.test" is already used by company #1, skipped',
            'company#6: e-mail address "other@acme.test" moved here from the deleted company #5',
        ], $summary->warnings);

        // Re-running is idempotent: the same records keep the address.
        $again = (new CompaniesImporter)->run(withHistory: false);
        activity()->enableLogging();
        $this->assertSame($expected, $emails());
        $this->assertSame([
            'company#2: e-mail address "info@acme.test" is already used by company #1, skipped',
            'company#5: e-mail address "other@acme.test" is already used by company #6, skipped',
        ], $again->warnings);
    }

    /**
     * rc_multiple_emails' extra addresses are absorbed into the owner's own
     * E-mail addresses collection now (Importer::legacyExtraEmails()) rather
     * than Mail's separate table.
     */
    public function test_a_companys_extra_address_from_rc_multiple_emails_becomes_an_other_item(): void
    {
        $this->legacyRecordTable('company_data_1');
        DB::connection('legacy')->table('company_data_1')->insert([
            'id' => 1, 'created_on' => '2020-01-01 10:00:00', 'f_company_name' => 'Acme', 'f_email' => 'info@acme.test',
        ]);
        $this->legacy()->create('rc_multiple_emails_data_1', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('active')->default(1);
            $t->text('f_record_type');
            $t->integer('f_record_id');
            $t->text('f_email');
        });
        DB::connection('legacy')->table('rc_multiple_emails_data_1')->insert(['id' => 1, 'f_record_type' => 'company', 'f_record_id' => 1, 'f_email' => 'Sales@acme.test']);

        activity()->disableLogging();
        (new CompaniesImporter)->run(withHistory: false);
        activity()->enableLogging();

        $company = Company::withTrashed()->where('legacy_id', 1)->sole();
        $this->assertSame(['info@acme.test', 'sales@acme.test'], $company->emails->pluck('value')->all());
        $this->assertSame(['work', 'other'], $company->emails->pluck('kind')->all());
    }

    public function test_a_contact_email_an_earlier_contact_already_has_is_skipped_and_reported(): void
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

        $emails = Contact::withTrashed()->orderBy('legacy_id')->get()
            ->mapWithKeys(fn (Contact $c): array => [$c->legacy_id => $c->emails->first()?->value])->all();
        $this->assertSame([1 => 'ann@acme.test', 2 => null, 3 => null], $emails);
        $this->assertSame(['contact#2: e-mail address "ann@acme.test" is already used by contact #1, skipped'], $summary->warnings);
    }

    public function test_a_contact_takes_the_address_it_shares_with_its_own_company_only(): void
    {
        $this->legacyRecordTable('company_data_1');
        $this->legacyRecordTable('contact_data_1');
        DB::connection('legacy')->table('company_data_1')->insert([
            ['id' => 1, 'created_on' => '2020-01-01 10:00:00', 'f_company_name' => 'Radpak', 'f_email' => 'd.gniadzik@radpak.test', 'active' => 1],
            ['id' => 2, 'created_on' => '2020-01-01 10:00:00', 'f_company_name' => 'Other', 'f_email' => 'office@other.test', 'active' => 1],
        ]);
        DB::connection('legacy')->table('contact_data_1')->insert([
            ['id' => 1, 'created_on' => '2020-01-02 10:00:00', 'f_last_name' => 'Gniadzik', 'f_first_name' => 'Dorota', 'f_company_name' => '1', 'f_email' => 'd.gniadzik@radpak.test', 'active' => 1],
            ['id' => 2, 'created_on' => '2020-01-02 10:00:00', 'f_last_name' => 'Stranger', 'f_first_name' => 'Sam', 'f_company_name' => '1', 'f_email' => 'office@other.test', 'active' => 1],
        ]);

        activity()->disableLogging();
        (new CompaniesImporter)->run(withHistory: false);
        $summary = (new ContactsImporter)->run(withHistory: false);
        activity()->enableLogging();

        $this->assertSame('d.gniadzik@radpak.test', Contact::where('legacy_id', 1)->sole()->emails->first()?->value);
        $this->assertNull(Company::where('legacy_id', 1)->sole()->emails->first());
        // Not its company: the address stays where it is.
        $this->assertNull(Contact::where('legacy_id', 2)->sole()->emails->first());
        $this->assertSame('office@other.test', Company::where('legacy_id', 2)->sole()->emails->first()?->value);
        $this->assertSame([
            'contact#1: e-mail address "d.gniadzik@radpak.test" moved here from its own company #1',
            'contact#2: e-mail address "office@other.test" is already used by company #2, skipped',
        ], $summary->warnings);
    }

    public function test_groups_are_checked_against_legacy_s_own_list_not_the_installed_one(): void
    {
        $this->legacy()->create('utils_commondata_tree', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('parent_id');
            $t->string('akey');
            $t->text('value')->nullable();
            $t->boolean('readonly')->default(false);
            $t->integer('position')->default(0);
        });
        // "partner" was added by an administrator in Epesi, so the installed
        // default list doesn't have it; "gone" was deleted from Epesi's list.
        DB::connection('legacy')->table('utils_commondata_tree')->insert([
            ['id' => 1, 'parent_id' => -1, 'akey' => 'Companies_Groups', 'value' => null],
            ['id' => 2, 'parent_id' => 1, 'akey' => 'customer', 'value' => 'Customer'],
            ['id' => 3, 'parent_id' => 1, 'akey' => 'partner', 'value' => 'Partner'],
            ['id' => 4, 'parent_id' => -1, 'akey' => 'Contacts_Groups', 'value' => null],
            ['id' => 5, 'parent_id' => 4, 'akey' => 'custm', 'value' => 'Customer'],
            ['id' => 6, 'parent_id' => 4, 'akey' => 'partner', 'value' => 'Partner'],
        ]);
        $this->legacyRecordTable('company_data_1');
        DB::connection('legacy')->table('company_data_1')->insert(['id' => 1, 'created_on' => '2020-01-01 10:00:00', 'f_company_name' => 'Acme', 'f_group' => '__customer__partner__gone__']);
        $this->legacyRecordTable('contact_data_1');
        DB::connection('legacy')->table('contact_data_1')->insert(['id' => 1, 'created_on' => '2020-01-01 10:00:00', 'f_last_name' => 'Buyer', 'f_first_name' => 'Ann', 'f_group' => '__custm__partner__gone__']);

        activity()->disableLogging();
        (new CompaniesImporter)->run(withHistory: false);
        (new ContactsImporter)->run(withHistory: false);
        activity()->enableLogging();

        $this->assertSame(['customer', 'partner'], Company::withTrashed()->where('legacy_id', 1)->sole()->groups);
        $this->assertSame(['customer', 'partner'], Contact::withTrashed()->where('legacy_id', 1)->sole()->groups, '"custm" renamed after the check');
    }

    public function test_a_mail_links_to_a_record_of_a_module_registered_tab(): void
    {
        [, , , $task] = $this->coreRecords();
        $this->buildLegacyMail();
        // Stands in for a module's recordset (tickets, projects...): mail's
        // own list doesn't name it, its registered importer does.
        $importer = new class extends Importer
        {
            public function legacyTab(): string
            {
                return 'premium_widgets';
            }

            public function modelClass(): string
            {
                return Task::class;
            }

            public function logName(): string
            {
                return 'task';
            }

            protected function trackedFields(): array
            {
                return [];
            }

            protected function decodeTrackedValue(string $legacyField, ?string $raw): array
            {
                return [];
            }
        };
        app(ImporterRegistry::class)->register('widgets', $importer::class);
        DB::connection('legacy')->table('rc_mails_data_1')->where('id', 12)->update(['f_related' => '__premium_widgets/9__']);

        $this->artisan('import:legacy', ['tab' => 'mail'])->assertSuccessful();

        $reply = Mail::query()->where('legacy_id', 12)->sole();
        $this->assertTrue($reply->linkedRecords()->contains(fn ($record) => $record->is($task)));
    }

    public function test_the_mail_tables_are_compared_row_by_row_and_a_row_nothing_explains_shows(): void
    {
        $this->coreRecords();
        $this->buildLegacyMail();
        $this->artisan('import:legacy', ['tab' => 'mail'])->assertSuccessful();

        $legacy = DB::connection('legacy');
        $tables = collect(app(MailImporter::class)->countedTables())->keyBy('table');
        $count = fn (string $table, array $absent = []): array => $tables[$table]['count']($legacy, $absent);

        $attachments = $legacy->table('rc_mails_attachments')->count();
        $this->assertSame(['source' => $attachments, 'imported' => $attachments, 'skipped' => 0, 'missing_ids' => 0], $count('rc_mails_attachments'));
        $legacy->table('rc_mails_attachments')->insert(['mail_id' => 11, 'name' => 'gone.pdf', 'mime_id' => 'gone']);
        $this->assertSame(1, $count('rc_mails_attachments')['missing_ids'], 'no attachment and the ledger does not say it was never there');
        $known = $count('rc_mails_attachments', ['CRM_Mail/attachments/11/gone' => true]);
        $this->assertSame([1, 0], [$known['skipped'], $known['missing_ids']]);

        $this->assertSame(['source' => 1, 'imported' => 1, 'skipped' => 0, 'missing_ids' => 0], $count('rc_accounts_data_1'));
        $legacy->table('rc_accounts_data_1')->insert([
            ['id' => 2, 'active' => 0, 'f_epesi_user' => '7', 'f_email' => 'old@ours.test'],
            ['id' => 3, 'active' => 1, 'f_epesi_user' => '99', 'f_email' => 'lost@ours.test'],
        ]);
        $this->assertSame(['source' => 3, 'imported' => 1, 'skipped' => 1, 'missing_ids' => 1], $count('rc_accounts_data_1'));

        $this->legacy()->create('rc_mail_threads_data_1', fn (Blueprint $t) => $t->integer('id'));
        $legacy->table('rc_mail_threads_data_1')->insert([['id' => 4], ['id' => 5]]);
        $this->assertSame(['source' => 2, 'imported' => 1, 'skipped' => 1, 'missing_ids' => 0], $count('rc_mail_threads_data_1'));
        Mail::query()->where('legacy_id', 12)->update(['thread_id' => null]);
        $this->assertSame(1, $count('rc_mail_threads_data_1')['missing_ids'], 'a live message without a thread');

        $this->legacy()->create('rc_multiple_emails_data_1', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('active')->default(1);
            foreach (['record_type', 'record_id', 'email'] as $f) {
                $t->text("f_{$f}")->nullable();
            }
        });
        $legacy->table('rc_multiple_emails_data_1')->insert([
            ['id' => 1, 'active' => 0, 'f_record_type' => 'contact', 'f_record_id' => '5', 'f_email' => 'old@acme.test'],
            ['id' => 2, 'active' => 1, 'f_record_type' => null, 'f_record_id' => null, 'f_email' => 'nobody@acme.test'],
            ['id' => 3, 'active' => 1, 'f_record_type' => 'contact', 'f_record_id' => '5', 'f_email' => 'lost@acme.test'],
        ]);
        $this->assertSame(['source' => 3, 'imported' => 0, 'skipped' => 2, 'missing_ids' => 1], $count('rc_multiple_emails_data_1'));

        $this->assertTrue($tables['rc_related_data_1']['optional'], 'an installation may not have it');
    }

    public function test_a_link_to_an_unported_recordset_keeps_the_mail_table_only_while_that_recordset_is_there(): void
    {
        $this->coreRecords();
        $this->buildLegacyMail();
        $this->artisan('import:legacy', ['tab' => 'mail'])->assertSuccessful();

        $legacy = DB::connection('legacy');
        $legacy->table('rc_mails_data_1')->where('id', 12)->update(['f_related' => '__premium_widgets/9__']);
        $pending = fn (): array => collect(app(MailImporter::class)->countedTables())->firstWhere('table', 'rc_mails_data_1')['pending']($legacy);

        $this->assertSame([], $pending(), 'the recordset is gone: the link can never be restored from here');

        $this->legacy()->create('premium_widgets_data_1', fn (Blueprint $t) => $t->integer('id'));
        $this->assertCount(1, $pending());
        $this->assertStringContainsString('premium_widgets', $pending()[0]);
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

    public function test_mail_attachments_are_moved_with_a_ledger_when_the_wizard_binds_the_moving_transfer(): void
    {
        $this->coreRecords();
        $this->buildLegacyMail();
        $state = $this->dataDir.'-state';
        $ledger = new FileLedger($state, $this->dataDir);
        app()->instance(LegacyFileTransfer::class, new MovingLegacyFileTransfer($ledger));

        try {
            $this->artisan('import:legacy', ['tab' => 'mail'])->assertSuccessful();

            $offer = Mail::query()->where('legacy_id', 11)->sole();
            $this->assertSame(['logo.png', 'offer.pdf'], $offer->attachments->pluck('name')->sort()->values()->all());
            $this->assertSame('%PDF offer', $offer->attachments->firstWhere('name', 'offer.pdf')->storedFile->read());
            $this->assertSame("\x89PNG fake", $offer->attachments->firstWhere('name', 'logo.png')->storedFile->read());
            $this->assertSame('2025-03-01 08:55:00', $offer->attachments->first()->storedFile->created_at->format('Y-m-d H:i:s'));

            // Both layouts were moved, not copied: the sources are gone, and the ledger accounts for each.
            $mime = str_repeat('a', 32);
            $this->assertFileDoesNotExist($this->dataDir.'/CRM_Mail/attachments/11/'.$mime);
            $sha = hash('sha512', '%PDF offer');
            $this->assertFileDoesNotExist($this->dataDir.'/Utils_FileStorage/'.implode('/', str_split(substr($sha, 0, 5))).'/'.substr($sha, 5));
            $this->assertCount(2, array_filter($ledger->entries(), fn (array $e): bool => $e['status'] === 'moved'));
            $this->assertSame([], $ledger->verify(fn (string $d) => Storage::disk($d), $this->dataDir)['problems']);

            // A resume re-walks the mails: the files resolve from the ledger, nothing is lost or doubled.
            $this->artisan('import:legacy', ['tab' => 'mail'])->assertSuccessful();
            $this->assertSame(2, $offer->fresh()->attachments()->count());
            $this->assertSame(2, StoredFile::count());
            $this->assertSame('%PDF offer', $offer->fresh()->attachments->firstWhere('name', 'offer.pdf')->storedFile->read());
        } finally {
            app()->forgetInstance(LegacyFileTransfer::class);
            File::deleteDirectory($state);
        }
    }

    public function test_a_mail_import_cut_into_slices_by_a_time_budget_ends_exactly_like_one_run(): void
    {
        $this->coreRecords();
        $this->buildLegacyMail();
        $checkpoint = $this->dataDir.'-checkpoint.json';
        config(['epesi-mail.legacy_checkpoint' => $checkpoint]);
        $budget = app(TimeBudget::class);

        $slices = 0;
        do {
            $budget->start(0.0); // used up after the first record of every slice
            try {
                app(MailImporter::class)->run(true);
                $finished = true;
            } catch (TimeBudgetReached) {
                $finished = false;
                $this->assertFileExists($checkpoint, 'a stopped slice saved its place');
            } finally {
                $budget->clear();
            }
            $slices++;
        } while (! $finished && $slices < 20);

        $this->assertGreaterThanOrEqual(3, $slices, 'two mails and the threads take more than one slice');
        $this->assertFileDoesNotExist($checkpoint, 'a finished import leaves no checkpoint');
        $offer = Mail::query()->where('legacy_id', 11)->sole();
        $reply = Mail::query()->where('legacy_id', 12)->sole();
        $this->assertSame(2, Mail::count());
        $this->assertSame($offer->thread_id, $reply->thread_id);
        $this->assertSame(2, $offer->thread->message_count);
        $this->assertSame(['logo.png', 'offer.pdf'], $offer->attachments->pluck('name')->sort()->values()->all());
        $this->assertSame(2, StoredFile::count());
    }

    public function test_wipe_is_a_dry_run_by_default_and_needs_confirmation_and_a_fresh_backup(): void
    {
        $this->coreRecords();
        $database = DB::connection()->getDatabaseName();
        $backup = $this->dataDir.'/dump.sql';
        File::put($backup, '-- dump');
        activity()->performedOn(Company::create(['company_name' => 'Gone']))->log('created');
        activity('module')->log('settings history stays');
        $before = Company::withTrashed()->count();

        $this->artisan('import:legacy:wipe')->assertSuccessful();
        $this->assertSame($before, Company::withTrashed()->count(), 'dry run changes nothing');

        $this->artisan('import:legacy:wipe', ['--force' => true, '--backup' => $backup])->assertFailed();
        $this->artisan('import:legacy:wipe', ['--force' => true, '--confirm' => 'other', '--backup' => $backup])->assertFailed();
        $this->artisan('import:legacy:wipe', ['--force' => true, '--confirm' => $database])->assertFailed();
        touch($backup, time() - 2 * 86400);
        $this->artisan('import:legacy:wipe', ['--force' => true, '--confirm' => $database, '--backup' => $backup])->assertFailed();
        $this->assertSame($before, Company::withTrashed()->count(), 'every refusal leaves the data alone');

        touch($backup);
        $this->artisan('import:legacy:wipe', ['--force' => true, '--confirm' => $database, '--backup' => $backup])->assertSuccessful();

        $this->assertSame(0, Company::withTrashed()->count());
        $this->assertSame(0, Contact::withTrashed()->count());
        $this->assertSame(0, Task::withTrashed()->count());
        $this->assertSame(0, DB::table('activity_log')->where('subject_type', 'company')->count());
        $this->assertGreaterThan(0, User::count(), 'users are settings, not wiped');
        $this->assertSame(1, DB::table('activity_log')->where('log_name', 'module')->count(), 'module history stays');
    }

    public function test_users_reimport_keeps_an_email_legacy_offers_unchanged_and_never_downgrades_to_invalid(): void
    {
        $this->legacy()->create('user_login', function (Blueprint $t) {
            $t->integer('id');
            $t->string('login');
            $t->integer('active')->default(1);
        });
        $this->legacy()->create('user_password', function (Blueprint $t) {
            $t->integer('user_login_id');
            $t->string('mail')->nullable();
        });
        $l = DB::connection('legacy');
        $l->table('user_login')->insert([['id' => 1, 'login' => 'jasiek'], ['id' => 2, 'login' => 'ghost'], ['id' => 3, 'login' => 'clash']]);
        // jasiek: legacy mail equals what the app already has; ghost: blank in legacy; clash: legacy offers jasiek's address.
        $l->table('user_password')->insert([
            ['user_login_id' => 1, 'mail' => 'j@epe.si'],
            ['user_login_id' => 2, 'mail' => ''],
            ['user_login_id' => 3, 'mail' => 'j@epe.si'],
        ]);

        User::factory()->create(['email' => 'j@epe.si'])->forceFill(['legacy_id' => 1])->save();
        User::factory()->create(['email' => 'ghost@real.test'])->forceFill(['legacy_id' => 2])->save();

        $this->artisan('import:legacy', ['tab' => 'users'])->assertSuccessful();

        $email = fn (int $legacyId): string => User::where('legacy_id', $legacyId)->sole()->email;
        $this->assertSame('j@epe.si', $email(1), 'own address offered back by legacy is not "taken"');
        $this->assertSame('ghost@real.test', $email(2), 'blank legacy mail keeps the local address');
        $this->assertSame('clash@imported.invalid', $email(3), 'another user\'s address still falls back');
    }

    public function test_login_audit_imports_sessions_from_a_legacy_without_a_device_column_and_is_rerunnable(): void
    {
        $this->legacy()->create('user_login', function (Blueprint $t) {
            $t->integer('id');
            $t->string('login');
            $t->integer('active')->default(1);
        });
        $this->legacy()->create('user_password', function (Blueprint $t) {
            $t->integer('user_login_id');
            $t->string('mail')->nullable();
        });
        // An older Epesi: no `device` column.
        $this->legacy()->create('base_login_audit', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('user_login_id');
            $t->dateTime('start_time');
            $t->dateTime('end_time')->nullable();
            $t->string('ip_address', 32)->nullable();
            $t->string('host_name')->nullable();
        });
        $l = DB::connection('legacy');
        $l->table('user_login')->insert([['id' => 1, 'login' => 'jirkaalfa']]);
        $l->table('user_password')->insert([['user_login_id' => 1, 'mail' => 'jirka@alfa.test']]);
        $l->table('base_login_audit')->insert([
            ['id' => 1, 'user_login_id' => 1, 'start_time' => '2017-02-06 10:04:16', 'end_time' => '2017-02-06 10:41:18', 'ip_address' => '109.105.49.20', 'host_name' => 'pc-1'],
            ['id' => 2, 'user_login_id' => 1, 'start_time' => '2017-02-07 08:00:00', 'end_time' => null, 'ip_address' => '::1', 'host_name' => null],
        ]);
        $mine = LoginAudit::query()->forceCreate([
            'login' => 'local', 'started_at' => '2026-01-01 10:00:00', 'ended_at' => '2026-01-01 10:05:00', 'ip_address' => '127.0.0.1',
        ]);

        $this->artisan('import:legacy', ['tab' => 'users'])->assertSuccessful();
        $this->artisan('import:legacy', ['tab' => 'loginaudit'])->assertSuccessful();
        $this->artisan('import:legacy', ['tab' => 'loginaudit'])->assertSuccessful();

        $this->assertSame(3, LoginAudit::count(), 'two sessions imported once, the local one kept');
        $first = LoginAudit::where('legacy_id', 1)->sole();
        $this->assertSame(User::where('legacy_id', 1)->value('id'), $first->user_id);
        $this->assertSame('jirkaalfa', $first->login);
        $this->assertSame('109.105.49.20', $first->ip_address);
        $this->assertNull($first->device);
        $this->assertEquals($first->started_at->copy()->setTime(10, 4, 16), $first->started_at);
        $this->assertSame('2017-02-07 08:00:00', LoginAudit::where('legacy_id', 2)->sole()->ended_at->format('Y-m-d H:i:s'), 'an open session ends where it started');
        $this->assertNull($mine->fresh()->legacy_id);
    }

    public function test_a_dealer_login_gets_the_dealer_role_and_a_deactivated_login_stays_deactivated(): void
    {
        $this->seed(RoleSeeder::class);
        $this->legacy()->create('user_login', function (Blueprint $t) {
            $t->integer('id');
            $t->string('login');
            $t->integer('active')->default(1);
            $t->integer('admin')->default(0);
        });
        $this->legacy()->create('user_password', function (Blueprint $t) {
            $t->integer('user_login_id');
            $t->string('mail')->nullable();
        });
        $this->legacyRecordTable('contact_data_1');
        $this->legacy()->table('contact_data_1', function (Blueprint $t) {
            $t->integer('f_login')->nullable();
            $t->text('f_access')->nullable();
        });
        $l = DB::connection('legacy');
        $l->table('user_login')->insert([
            ['id' => 1, 'login' => 'jirkaalfa', 'active' => 1],
            ['id' => 2, 'login' => 'alfa_renata', 'active' => 0],
            ['id' => 3, 'login' => 'janh', 'active' => 1],
        ]);
        $l->table('user_password')->insert([
            ['user_login_id' => 1, 'mail' => 'jirka@alfa.test'],
            ['user_login_id' => 2, 'mail' => 'renata@alfa.test'],
            ['user_login_id' => 3, 'mail' => 'jan@importer.test'],
        ]);
        $l->table('contact_data_1')->insert([
            ['id' => 1, 'created_on' => '2020-01-01 10:00:00', 'f_last_name' => 'Alfa', 'f_first_name' => 'Jirka', 'f_login' => 1, 'f_access' => '__dealer__'],
            ['id' => 2, 'created_on' => '2020-01-01 10:00:00', 'f_last_name' => 'Alfa', 'f_first_name' => 'Renata', 'f_login' => 2, 'f_access' => '__dealer__'],
            ['id' => 3, 'created_on' => '2020-01-01 10:00:00', 'f_last_name' => 'H', 'f_first_name' => 'Jan', 'f_login' => 3, 'f_access' => '__accountmanager__employee__manager__'],
        ]);

        $this->artisan('import:legacy', ['tab' => 'users'])->assertSuccessful();
        activity()->disableLogging();
        (new ContactsImporter)->run(withHistory: false);
        activity()->enableLogging();

        $user = fn (int $legacyId): User => User::where('legacy_id', $legacyId)->sole();
        $this->assertSame(['dealer'], $user(1)->getRoleNames()->all(), 'a dealer gets no staff role');
        $this->assertTrue($user(1)->active);
        $this->assertSame(['dealer'], $user(2)->getRoleNames()->all());
        $this->assertFalse($user(2)->active, 'deactivated in legacy, deactivated here');
        $this->assertEqualsCanonicalizing(['employee', 'manager'], $user(3)->getRoleNames()->all());
    }

    public function test_two_contacts_naming_the_same_login_import_with_the_link_on_the_active_one_only(): void
    {
        $this->seed(RoleSeeder::class);
        $this->legacy()->create('user_login', function (Blueprint $t) {
            $t->integer('id');
            $t->string('login');
            $t->integer('active')->default(1);
            $t->integer('admin')->default(0);
        });
        $this->legacy()->create('user_password', function (Blueprint $t) {
            $t->integer('user_login_id');
            $t->string('mail')->nullable();
        });
        $this->legacyRecordTable('contact_data_1');
        $this->legacy()->table('contact_data_1', function (Blueprint $t) {
            $t->integer('f_login')->nullable();
            $t->text('f_access')->nullable();
        });
        $l = DB::connection('legacy');
        $l->table('user_login')->insert([['id' => 6, 'login' => 'sue', 'active' => 1]]);
        $l->table('user_password')->insert([['user_login_id' => 6, 'mail' => 'sue@acme.test']]);
        // The older contact was deleted in legacy; the newer one is the live one. Both name login 6.
        $l->table('contact_data_1')->insert([
            ['id' => 1, 'created_on' => '2020-01-01 10:00:00', 'active' => 0, 'f_last_name' => 'Jovanov', 'f_first_name' => 'Sue', 'f_login' => 6],
            ['id' => 2, 'created_on' => '2020-02-01 10:00:00', 'active' => 1, 'f_last_name' => 'Jovanov', 'f_first_name' => 'Susan', 'f_login' => 6],
        ]);

        $this->artisan('import:legacy', ['tab' => 'users'])->assertSuccessful();
        activity()->disableLogging();
        $summary = (new ContactsImporter)->run(withHistory: false);
        activity()->enableLogging();

        $user = User::where('legacy_id', 6)->sole();
        $this->assertSame($user->id, Contact::where('legacy_id', 2)->sole()->user_id);
        $this->assertNull(Contact::withTrashed()->where('legacy_id', 1)->sole()->user_id);
        $this->assertCount(1, array_filter($summary->warnings, fn (string $w): bool => str_contains($w, 'contact#1: the login is already used by contact#2')));
    }

    /**
     * @return array{0: User, 1: Contact, 2: Company, 3: Task}
     */
    protected function coreRecords(): array
    {
        $user = User::factory()->create();
        $user->forceFill(['legacy_id' => 7])->save();

        $me = Contact::create(['first_name' => 'Eli', 'last_name' => 'Employee']);
        $me->forceFill(['legacy_id' => 1, 'user_id' => $user->id])->save();
        $me->syncCollection('emails', [['kind' => 'work', 'value' => 'eli@ours.test']]);
        $ann = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer']);
        $ann->forceFill(['legacy_id' => 5])->save();
        $ann->syncCollection('emails', [['kind' => 'work', 'value' => 'ann@acme.test']]);
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
            foreach (['company_name', 'last_name', 'first_name', 'email', 'group'] as $f) {
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
