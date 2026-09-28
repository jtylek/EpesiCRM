<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use App\Services\LegacyImport\Importers\CompaniesImporter;
use App\Services\LegacyImport\Importers\TasksImporter;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\EditCompany;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ListTasks;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\TaskResource;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldSchema;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Epesi\Modules\RecordBrowser\Models\RecordLink;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * FieldType::Related — "link to any record", legacy's `__RECORDSETS__` field:
 * the Related field on tasks, meetings and phone calls, and one an
 * administrator adds.
 */
class RelatedFieldTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_links_are_saved_and_removed(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $task = Task::create(['title' => 'Send offer']);
        $company = Company::create(['company_name' => 'Acme Ltd']);
        $contact = Contact::create(['last_name' => 'Buyer', 'first_name' => 'Ann']);

        $task->syncRecordLinks('related', ["company:{$company->id}", "contact:{$contact->id}"]);
        $this->assertSame(['company', 'contact'], $this->linkedAliases($task));

        $task->syncRecordLinks('related', ["contact:{$contact->id}"]);
        $this->assertSame(['contact'], $this->linkedAliases($task));

        // Anything that doesn't name a record here is ignored.
        $task->syncRecordLinks('related', ["contact:{$contact->id}", 'nonsense', 'company:999999', 'nosuchalias:1']);
        $this->assertSame(['contact'], $this->linkedAliases($task));

        // And so is a recordset the field doesn't offer.
        $task->syncRecordLinks('related', ["company:{$company->id}"], recordsets: ['contact']);
        $this->assertSame([], $this->linkedAliases($task));
    }

    public function test_a_link_the_user_cannot_see_is_hidden_and_survives_their_save(): void
    {
        $author = $this->userWithRole('employee');
        $other = $this->userWithRole('employee');

        $this->actingAs($author);
        $secret = Company::create(['company_name' => 'Secret Co', 'permission' => RecordPermission::Private]);
        $alsoSecret = Company::create(['company_name' => 'Also Secret', 'permission' => RecordPermission::Private]);
        $public = Company::create(['company_name' => 'Public Co']);
        $task = Task::create(['title' => 'Shared task']);
        $task->syncRecordLinks('related', ["company:{$secret->id}", "company:{$public->id}"]);

        $this->actingAs($other);
        $task = Task::query()->findOrFail($task->id);
        $this->assertSame(['Public Co'], $task->linkedRecords('related')->pluck('company_name')->all());

        // Saving what they see (nothing) doesn't remove what they can't...
        $task->syncRecordLinks('related', []);
        // ...and they can't link to a record they can't see either.
        $task->syncRecordLinks('related', ["company:{$alsoSecret->id}"]);

        $this->actingAs($author);
        $this->assertSame(['Secret Co'], Task::query()->findOrFail($task->id)->linkedRecords('related')->pluck('company_name')->all());
    }

    public function test_the_view_page_and_the_list_show_the_links(): void
    {
        $this->withoutVite();
        $this->actingAs($this->userWithRole('employee'));

        $company = Company::create(['company_name' => 'Acme Ltd']);
        $tasks = collect(range(1, 3))->map(function (int $n) use ($company): Task {
            $task = Task::create(['title' => "Task {$n}"]);
            $task->syncRecordLinks('related', ["company:{$company->id}"]);

            return $task;
        });

        $this->get(TaskResource::getUrl('view', ['record' => $tasks->first()]))
            ->assertOk()
            ->assertSee('Company: Acme Ltd');

        $list = Livewire::test(ListTasks::class)
            ->toggleAllTableColumns()
            ->assertCanSeeTableRecords($tasks)
            ->assertSee('Company: Acme Ltd');

        // One render loads the page's links in one query, not one per row.
        $linkQueries = 0;
        DB::listen(function ($query) use (&$linkQueries): void {
            if (str_contains($query->sql, 'epesi_recordbrowser_links')) {
                $linkQueries++;
            }
        });

        $list->call('$refresh')->assertSee('Company: Acme Ltd');

        $this->assertSame(1, $linkQueries);
    }

    public function test_the_search_offers_records_of_every_linkable_recordset(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        Filament::setCurrentPanel(Filament::getPanel('main'));

        $company = Company::create(['company_name' => 'Acme Ltd']);
        $contact = Contact::create(['last_name' => 'Acmeson', 'first_name' => 'Ann']);

        $field = collect(TaskResource::fields())->firstWhere('name', 'related');
        $results = $field->relatedSearch('Acme');

        $this->assertSame('Company: Acme Ltd', $results["company:{$company->id}"] ?? null);
        $this->assertArrayHasKey("contact:{$contact->id}", $results);
    }

    public function test_an_administrator_added_related_field_needs_no_column_and_saves_from_the_form(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $definition = CustomField::create([
            'model_type' => 'company',
            'name' => 'partners',
            'label' => 'Partners',
            'type' => FieldType::Related,
            'params' => ['recordsets' => ['contact']],
        ]);

        $this->assertFalse(Schema::hasColumn((new Company)->getTable(), $definition->column));

        $company = Company::create(['company_name' => 'Acme Ltd']);
        $contact = Contact::create(['last_name' => 'Buyer', 'first_name' => 'Ann']);

        Livewire::test(EditCompany::class, ['record' => $company->getRouteKey()])
            ->fillForm([$definition->column => ["contact:{$contact->id}"]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$contact->id], $company->fresh()->linkedRecords($definition->column)->modelKeys());

        // Dropping the field drops its links, as a column's values go with it.
        app(CustomFieldSchema::class)->drop($definition);
        $this->assertSame(0, RecordLink::query()->where('field', $definition->column)->count());
    }

    public function test_a_record_deleted_for_good_takes_its_links_with_it(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $company = Company::create(['company_name' => 'Acme Ltd']);
        $task = Task::create(['title' => 'Send offer']);
        $task->syncRecordLinks('related', ["company:{$company->id}"]);

        $task->delete();
        $this->assertSame(1, RecordLink::query()->count());

        $task->forceDelete();
        $this->assertSame(0, RecordLink::query()->count());
    }

    public function test_the_import_links_what_was_imported_and_reports_the_rest(): void
    {
        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('legacy');
        $legacy = Schema::connection('legacy');

        $legacy->create('company_data_1', function (Blueprint $t) {
            $t->integer('id');
            $t->dateTime('created_on');
            $t->integer('created_by')->nullable();
            $t->integer('active')->default(1);
            foreach (['company_name', 'short_name', 'phone', 'fax', 'email', 'web_address', 'memo', 'group', 'permission',
                'address_1', 'address_2', 'city', 'country', 'zone', 'postal_code', 'tax_id'] as $f) {
                $t->text("f_{$f}")->nullable();
            }
        });
        $legacy->create('task_data_1', function (Blueprint $t) {
            $t->integer('id');
            $t->dateTime('created_on');
            $t->integer('created_by')->nullable();
            $t->integer('active')->default(1);
            foreach (['title', 'description', 'status', 'priority', 'permission', 'deadline', 'timeless',
                'employees', 'customers', 'related'] as $f) {
                $t->text("f_{$f}")->nullable();
            }
        });

        DB::connection('legacy')->table('company_data_1')->insert(['id' => 3, 'created_on' => '2020-01-01 10:00:00', 'f_company_name' => 'Acme Ltd']);
        DB::connection('legacy')->table('task_data_1')->insert([
            'id' => 7, 'created_on' => '2020-01-02 10:00:00', 'f_title' => 'Send offer', 'f_deadline' => '',
            'f_related' => '__company/3__unported_recordset/8__',
        ]);

        activity()->disableLogging();
        (new CompaniesImporter)->run(withHistory: false);
        $summary = (new TasksImporter)->run(withHistory: false);
        activity()->enableLogging();

        $task = Task::withTrashed()->where('legacy_id', 7)->sole();
        $company = Company::withTrashed()->where('legacy_id', 3)->sole();

        $this->assertSame([$company->id], $task->linkedRecords('related')->modelKeys());
        $this->assertContains('task#7: related "unported_recordset/8" isn\'t imported here, link skipped', $summary->warnings);
    }

    /**
     * @return list<string>
     */
    protected function linkedAliases(Task $task): array
    {
        return $task->linkedRecords('related')->map(fn ($record): string => $record->getMorphClass())->all();
    }
}
