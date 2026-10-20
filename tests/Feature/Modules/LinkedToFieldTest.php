<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use App\Services\LegacyImport\Importers\CompaniesImporter;
use App\Services\LegacyImport\Importers\TasksImporter;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\EditCompany;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\EditTask;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages\ListTasks;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\TaskResource;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\RecordBrowser\Browsing\RecentRecords;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldSchema;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Epesi\Modules\RecordBrowser\Models\RecordLink;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\FieldOverrides;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Epesi\Modules\RecordBrowser\Recordset\IncomingLinks;
use Epesi\Modules\RecordBrowser\Recordset\LinkableRecordsets;
use Epesi\Modules\RecordBrowser\Recordset\LinkedTo;
use Epesi\Modules\RecordBrowser\Recordset\RecordPicker;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * FieldType::Related, shown as "Linked to" (Field::linkedTo()) — legacy's
 * `__RECORDSETS__` field: the Linked to field on tasks, meetings and phone
 * calls, and one an administrator adds. See step 12 in
 * AI-shared/Epesi-custom-fields.md.
 */
class LinkedToFieldTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function tearDown(): void
    {
        LinkedTo::reset();

        parent::tearDown();
    }

    public function test_links_are_saved_and_removed(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $task = Task::create(['title' => 'Send offer']);
        $company = Company::create(['company_name' => 'Acme Ltd']);
        $contact = Contact::create(['last_name' => 'Buyer', 'first_name' => 'Ann']);

        $task->syncRecordLinks('linked_to', ["company:{$company->id}", "contact:{$contact->id}"]);
        $this->assertSame(['company', 'contact'], $this->linkedAliases($task));

        $task->syncRecordLinks('linked_to', ["contact:{$contact->id}"]);
        $this->assertSame(['contact'], $this->linkedAliases($task));

        // Anything that doesn't name a record here is ignored.
        $task->syncRecordLinks('linked_to', ["contact:{$contact->id}", 'nonsense', 'company:999999', 'nosuchalias:1']);
        $this->assertSame(['contact'], $this->linkedAliases($task));

        // And so is a recordset the field doesn't offer.
        $task->syncRecordLinks('linked_to', ["company:{$company->id}"], recordsets: ['contact']);
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
        $task->syncRecordLinks('linked_to', ["company:{$secret->id}", "company:{$public->id}"]);

        $this->actingAs($other);
        $task = Task::query()->findOrFail($task->id);
        $this->assertSame(['Public Co'], $task->linkedRecords('linked_to')->pluck('company_name')->all());

        // Saving what they see (nothing) doesn't remove what they can't...
        $task->syncRecordLinks('linked_to', []);
        // ...and they can't link to a record they can't see either.
        $task->syncRecordLinks('linked_to', ["company:{$alsoSecret->id}"]);

        $this->actingAs($author);
        $this->assertSame(['Secret Co'], Task::query()->findOrFail($task->id)->linkedRecords('linked_to')->pluck('company_name')->all());
    }

    public function test_the_view_page_and_the_list_show_the_links(): void
    {
        $this->withoutVite();
        $this->actingAs($this->userWithRole('employee'));

        $company = Company::create(['company_name' => 'Acme Ltd']);
        $tasks = collect(range(1, 3))->map(function (int $n) use ($company): Task {
            $task = Task::create(['title' => "Task {$n}"]);
            $task->syncRecordLinks('linked_to', ["company:{$company->id}"]);

            return $task;
        });

        $this->get(TaskResource::getUrl('view', ['record' => $tasks->first()]))
            ->assertOk()
            ->assertSee('Company: Acme Ltd');

        $list = Livewire::test(ListTasks::class)
            ->toggleAllTableColumns()
            ->assertCanSeeTableRecords($tasks)
            ->assertSee('Company: Acme Ltd');

        // One render loads the page's links in one query, not one per row —
        // matched by table position rather than mere substring, since Task's
        // ownership scope (customers(), now a MorphToMany over this same
        // table) legitimately mentions it in a whereHas() subquery on every
        // Task query too.
        $linkQueries = 0;
        DB::listen(function ($query) use (&$linkQueries): void {
            if (str_starts_with($query->sql, 'select * from "epesi_recordbrowser_links"')) {
                $linkQueries++;
            }
        });

        $list->call('$refresh')->assertSee('Company: Acme Ltd');

        $this->assertSame(1, $linkQueries);
    }

    public function test_the_picker_searches_one_recordset_and_leaves_out_what_it_must(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        Filament::setCurrentPanel(Filament::getPanel('main'));

        $acme = Company::create(['company_name' => 'Acme Ltd']);
        $acmeTwo = Company::create(['company_name' => 'Acme Two']);
        Contact::create(['last_name' => 'Acmeson', 'first_name' => 'Ann']);

        // One recordset at a time, by its title.
        $this->assertSame([$acme->id => 'Acme Ltd', $acmeTwo->id => 'Acme Two'], RecordPicker::search('company', 'Acme'));
        // Without what is excluded (the record itself, those already linked).
        $this->assertSame([$acmeTwo->id => 'Acme Two'], RecordPicker::search('company', 'Acme', [$acme->id]));
        $this->assertSame([], RecordPicker::search('company', ''));
        $this->assertSame([], RecordPicker::search('nosuchalias', 'Acme'));
    }

    public function test_the_picker_lists_recent_records_only_for_a_recordset_that_keeps_them(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('main'));

        $first = Company::create(['company_name' => 'First Co']);
        $second = Company::create(['company_name' => 'Second Co']);
        Company::create(['company_name' => 'Never opened']);

        $this->assertTrue(RecordPicker::hasRecent('company'));
        RecentRecords::visit($user, $first, 50);
        $this->travel(1)->minutes();
        RecentRecords::visit($user, $second, 50);

        // The latest first, and only what this user opened.
        $this->assertSame([$second->id => 'Second Co', $first->id => 'First Co'], RecordPicker::recent('company'));
        $this->assertSame([$first->id => 'First Co'], RecordPicker::recent('company', [$second->id]));

        $without = collect(array_keys(LinkableRecordsets::options()))->first(fn (string $alias): bool => ! RecordPicker::hasRecent($alias));

        if ($without !== null) {
            $this->assertSame([], RecordPicker::recent($without));
        }
    }

    public function test_each_recordset_is_offered_with_its_icon_and_an_escaped_label(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        Filament::setCurrentPanel(Filament::getPanel('main'));

        $options = RecordPicker::recordsetOptions(['company', 'contact', 'nosuchalias']);

        $this->assertSame(['company', 'contact'], array_keys($options));
        $this->assertStringContainsString('<svg', $options['company']);
        $this->assertStringEndsWith(e(LinkableRecordsets::label('company')), $options['company']);
        $this->assertStringEndsWith('&lt;b&gt;x&lt;/b&gt;', LinkableRecordsets::iconLabel('company', '<b>x</b>'));
    }

    public function test_tasks_link_to_companies_and_contacts_by_default(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $this->assertSame(['company', 'contact'], $this->linkedToField()->relatedRecordsets());
        $this->assertSame('Linked to', $this->linkedToField()->getLabel());
    }

    public function test_another_module_offers_its_recordset_and_the_administrator_overrides_both(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        $overrides = app(FieldOverrides::class);

        LinkedTo::offer('task', 'linked_to', Task::class);
        $this->assertSame(['company', 'contact', 'task'], $this->linkedToField()->relatedRecordsets());

        // The administrator's list replaces the module's and the offers...
        $overrides->save('task', 'linked_to', ['recordsets' => ['contact']]);
        $this->assertSame(['contact'], $this->linkedToField()->relatedRecordsets());

        // ...in either direction.
        $overrides->save('task', 'linked_to', ['recordsets' => LinkedTo::ANY]);
        $this->assertSame(array_keys(LinkableRecordsets::options()), $this->linkedToField()->relatedRecordsets());

        // Back to the module's default.
        $overrides->save('task', 'linked_to', []);
        $this->assertSame(['company', 'contact', 'task'], $this->linkedToField()->relatedRecordsets());
    }

    public function test_the_administrator_must_pick_linkable_recordsets(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));

        foreach ([[], ['nosuchalias'], 'contact'] as $recordsets) {
            try {
                app(FieldOverrides::class)->save('task', 'linked_to', ['recordsets' => $recordsets]);
                $this->fail('Saved recordsets '.json_encode($recordsets));
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        // Only a Linked to field takes the setting.
        $this->expectException(ValidationException::class);
        app(FieldOverrides::class)->save('task', 'title', ['recordsets' => LinkedTo::ANY]);
    }

    public function test_a_record_links_to_others_of_its_recordset_but_never_to_itself(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $task = Task::create(['title' => 'Main task']);
        $other = Task::create(['title' => 'Other task']);

        $task->syncRecordLinks('linked_to', ["task:{$task->id}", "task:{$other->id}"]);

        $this->assertSame([$other->id], $task->linkedRecords('linked_to')->modelKeys());
    }

    public function test_the_popup_links_a_record_and_the_form_saves_it(): void
    {
        $this->withoutVite();
        $this->actingAs($this->userWithRole('employee'));

        $task = Task::create(['title' => 'Send offer']);
        $contact = Contact::create(['last_name' => 'Buyer', 'first_name' => 'Ann']);
        $company = Company::create(['company_name' => 'Acme Ltd']);
        $task->syncRecordLinks('linked_to', ["company:{$company->id}"]);
        $task->employees()->attach(Contact::create(['last_name' => 'Staff', 'first_name' => 'Sam']));

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->assertSee('Acme Ltd')
            ->assertSee('Link a record')
            ->callAction(TestAction::make('linkRecord')->schemaComponent('linked_to'), data: ['recordset' => 'contact', 'record' => $contact->id])
            ->assertHasNoFormErrors()
            ->assertSet('data.linked_to', ["company:{$company->id}", "contact:{$contact->id}"])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing([$company->id, $contact->id], $task->fresh()->linkedRecords('linked_to')->modelKeys());
    }

    public function test_the_popup_refuses_a_recordset_the_field_does_not_offer_and_the_record_itself(): void
    {
        $this->withoutVite();
        $this->actingAs($this->userWithRole('employee'));
        LinkedTo::offer('task', 'linked_to', Task::class);

        $task = Task::create(['title' => 'Send offer']);
        $other = Task::create(['title' => 'Prepare offer']);
        $meeting = Meeting::create(['title' => 'Kick-off', 'date' => '2026-10-08', 'time' => '10:00:00', 'duration_minutes' => 60]);

        $link = TestAction::make('linkRecord')->schemaComponent('linked_to');

        // The record itself: not linked.
        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->callAction($link, data: ['recordset' => 'task', 'record' => $task->id])
            ->assertSet('data.linked_to', []);

        // A recordset the field doesn't offer: refused by the popup's own validation.
        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->callAction($link, data: ['recordset' => 'meeting', 'record' => $meeting->id])
            ->assertHasActionErrors(['recordset'])
            ->assertSet('data.linked_to', []);

        // Another task: linked.
        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->callAction($link, data: ['recordset' => 'task', 'record' => $other->id])
            ->assertSet('data.linked_to', ["task:{$other->id}"]);
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
        $task->syncRecordLinks('linked_to', ["company:{$company->id}"]);

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

        $this->assertSame([$company->id], $task->linkedRecords('linked_to')->modelKeys());
        $this->assertContains('task#7: related "unported_recordset/8" isn\'t imported here, link skipped', $summary->warnings);
    }

    public function test_a_change_of_links_is_one_history_entry_with_the_records_by_title(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        Filament::setCurrentPanel(Filament::getPanel('main'));

        $task = Task::create(['title' => 'Send offer']);
        $task->employees()->attach(Contact::create(['last_name' => 'Staff', 'first_name' => 'Sam']));
        $acme = Company::create(['company_name' => 'Acme Ltd']);
        $ann = Contact::create(['last_name' => 'Buyer', 'first_name' => 'Ann']);
        $task->syncRecordLinks('linked_to', ["company:{$acme->id}"]);
        $entries = fn () => $task->activities()->orderBy('id')->get();
        $before = $entries()->count();

        // The title and a link together: one entry.
        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->fillForm(['title' => 'Send the offer', 'linked_to' => ["contact:{$ann->id}"]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertCount($before + 1, $entries());
        $entry = $entries()->last();
        $this->assertEqualsCanonicalizing(['title', 'linked_to'], array_keys($entry->properties['attributes']));
        $this->assertSame(["company:{$acme->id}"], $entry->properties['old']['linked_to']);
        $this->assertSame(["contact:{$ann->id}"], $entry->properties['attributes']['linked_to']);

        // Shown by title: the record taken off, struck through, and the one linked.
        $change = $this->linkedToField()->formatLoggedChange($entry->properties['old']['linked_to'], $entry->properties['attributes']['linked_to'], $entry)->toHtml();
        $this->assertStringContainsString('<del class="epesi-history-old">− Company: Acme Ltd</del>', $change);
        $this->assertStringContainsString('<ins class="epesi-history-new">+ Contact: ', $change);

        // Saved again as it is: nothing logged.
        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])->call('save')->assertHasNoFormErrors();
        $this->assertCount($before + 1, $entries());
    }

    public function test_history_never_names_a_linked_record_its_reader_cannot_see(): void
    {
        $author = $this->userWithRole('employee');
        $this->actingAs($author);
        Filament::setCurrentPanel(Filament::getPanel('main'));
        $secret = Company::create(['company_name' => 'Secret Co', 'permission' => RecordPermission::Private]);
        $task = Task::create(['title' => 'Shared task']);
        $task->syncRecordLinks('linked_to', ["company:{$secret->id}"]);
        $entry = $task->activities()->latest('id')->first();

        $this->assertSame('Company: Secret Co', $this->linkedToField()->formatLoggedValue($entry->properties['attributes']['linked_to']));

        $this->actingAs($this->userWithRole('employee'));
        $this->assertSame("Company #{$secret->id}", $this->linkedToField()->formatLoggedValue($entry->properties['attributes']['linked_to']));
    }

    public function test_records_of_the_same_recordset_linking_here_get_a_linked_from_tab(): void
    {
        $this->withoutVite();
        $this->actingAs($this->userWithRole('employee'));
        LinkedTo::offer('task', 'linked_to', Task::class);

        $task = Task::create(['title' => 'Main task']);
        $other = Task::create(['title' => 'Follow-on task']);
        $other->syncRecordLinks('linked_to', ["task:{$task->id}"]);

        $this->assertSame('Linked from: Tasks', IncomingLinks::tabLabel(TaskResource::class, $task));
        $this->assertSame('Tasks', IncomingLinks::tabLabel(TaskResource::class, Company::create(['company_name' => 'Acme'])));

        $this->get(TaskResource::getUrl('view', ['record' => $task]))
            ->assertOk()
            ->assertSee('Linked from: Tasks');
    }

    protected function linkedToField(): Field
    {
        return collect(TaskResource::resolvedFields())->firstWhere('name', 'linked_to');
    }

    /**
     * @return list<string>
     */
    protected function linkedAliases(Task $task): array
    {
        return $task->linkedRecords('linked_to')->map(fn ($record): string => $record->getMorphClass())->all();
    }
}
