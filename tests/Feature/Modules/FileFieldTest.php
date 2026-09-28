<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use App\Models\StoredFile;
use App\Services\FileStorage;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\CompanyResource;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\EditCompany;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\ListCompanies;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * FieldType::File — a record's files in the shared file storage, through an
 * administrator-added field on Companies (the same code path a module's
 * Field::file() takes).
 */
class FileFieldTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_a_file_field_is_a_json_column_of_stored_file_ids(): void
    {
        $column = $this->addFileField()->column;

        $this->assertTrue(Schema::hasColumn((new Company)->getTable(), $column));

        $file = app(FileStorage::class)->put('%PDF offer', 'offer.pdf');
        $company = Company::create(['company_name' => 'Acme Corp', $column => [$file->id]]);

        $fresh = $company->fresh();
        // Strings, as FileUpload's state and tamper check want them.
        $this->assertSame([(string) $file->id], $fresh->{$column});
        $this->assertSame(['offer.pdf'], $fresh->storedFiles($column)->pluck('name')->all());

        // No files is null, which the "has files" filter relies on.
        $fresh->update([$column => []]);
        $this->assertNull($fresh->fresh()->getRawOriginal($column));
    }

    public function test_the_form_uploads_into_the_file_storage(): void
    {
        $this->addFileField(['multiple' => false, 'max_size_mb' => 5]);

        $upload = CustomFieldRegistry::fieldsFor(Company::class)[0]->toFormComponent();

        $this->assertInstanceOf(FileUpload::class, $upload);
        $this->assertFalse($upload->isMultiple());
        $this->assertSame(5 * 1024, $upload->getMaxSize());
    }

    public function test_the_view_page_and_the_list_show_the_files(): void
    {
        $this->withoutVite();
        $column = $this->addFileField(attributes: ['show_in_table' => true])->column;
        $this->actingAs($this->userWithRole('employee'));

        $file = app(FileStorage::class)->put('%PDF offer', 'offer.pdf');
        $company = Company::create(['company_name' => 'Acme Corp', $column => [$file->id]]);
        $url = CustomFieldRegistry::fieldsFor(Company::class)[0]->fileUrl($company, $file);

        $this->get(CompanyResource::getUrl('view', ['record' => $company]))
            ->assertOk()
            ->assertSee('offer.pdf')
            ->assertSee($url, escape: false);

        Livewire::test(ListCompanies::class)
            ->assertCanSeeTableRecords([$company])
            ->assertSee('offer.pdf');
    }

    public function test_saving_the_edit_form_keeps_the_files_it_was_opened_with(): void
    {
        $column = $this->addFileField()->column;
        $this->actingAs($this->userWithRole('employee'));

        $file = app(FileStorage::class)->put('%PDF offer', 'offer.pdf');
        $company = Company::create(['company_name' => 'Acme Corp', $column => [$file->id]]);

        // The tamper check lets the record's own files through only when the
        // hydrated ids compare equal to the saved ones.
        Livewire::test(EditCompany::class, ['record' => $company->getRouteKey()])
            ->fillForm(['company_name' => 'Acme Corporation'])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $company->fresh();
        $this->assertSame('Acme Corporation', $fresh->company_name);
        $this->assertSame([(string) $file->id], $fresh->{$column});
        $this->assertNotNull(StoredFile::find($file->id));
    }

    public function test_a_file_is_served_only_to_someone_who_may_see_the_record(): void
    {
        $column = $this->addFileField()->column;
        $author = $this->userWithRole('employee');
        $other = $this->userWithRole('employee');

        $this->actingAs($author);
        $file = app(FileStorage::class)->put('%PDF secret', 'secret.pdf');
        $company = Company::create(['company_name' => 'Acme Corp', 'permission' => RecordPermission::Private, $column => [$file->id]]);
        $url = CustomFieldRegistry::fieldsFor(Company::class)[0]->fileUrl($company, $file);
        $this->assertSame($this->fileUrl($company, $column, $file), $url);

        $this->get($url)->assertOk()->assertDownload('secret.pdf');

        // A file this field doesn't hold isn't served through the record...
        $stranger = app(FileStorage::class)->put('elsewhere', 'elsewhere.txt');
        $this->get($this->fileUrl($company, $column, $stranger))->assertNotFound();
        // ...and nor is anything through a column that holds no files.
        $this->get($this->fileUrl($company, 'company_name', $file))->assertNotFound();

        // Someone else's private record doesn't exist for them.
        $this->actingAs($other);
        $this->get($url)->assertNotFound();

        auth()->logout();
        $this->get($url)->assertForbidden();
    }

    public function test_only_a_previewable_file_opens_in_the_browser(): void
    {
        $column = $this->addFileField()->column;
        $this->actingAs($this->userWithRole('employee'));

        $storage = app(FileStorage::class);
        $text = $storage->put('plain words', 'notes.txt');
        $page = $storage->put('<html><script>alert(1)</script></html>', 'page.html');
        $company = Company::create(['company_name' => 'Acme Corp', $column => [$text->id, $page->id]]);

        $this->get($this->fileUrl($company, $column, $text, preview: true))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline', (string) $this->get($this->fileUrl($company, $column, $text, preview: true))->headers->get('Content-Disposition'));

        // Not previewable: downloaded, preview asked for or not.
        $this->assertStringStartsWith('attachment', (string) $this->get($this->fileUrl($company, $column, $page, preview: true))->headers->get('Content-Disposition'));
    }

    public function test_a_file_taken_off_is_released_and_history_still_names_it(): void
    {
        $column = $this->addFileField()->column;
        $this->actingAs($this->userWithRole('employee'));

        $storage = app(FileStorage::class);
        $keep = $storage->put('keep', 'keep.txt');
        $drop = $storage->put('drop', 'drop.txt');
        $company = Company::create(['company_name' => 'Acme Corp', $column => [$keep->id, $drop->id]]);

        $company->update([$column => [$keep->id]]);

        $this->assertNull(StoredFile::find($drop->id));
        $this->assertNotNull(StoredFile::find($keep->id));

        $entry = $company->activities()->latest('id')->first();
        $change = CustomFieldRegistry::fieldsFor(Company::class)[0]->formatLoggedChange(
            data_get($entry->properties, "old.{$column}"),
            data_get($entry->properties, "attributes.{$column}"),
            $entry,
        );

        $this->assertStringContainsString('drop.txt', (string) $change?->toHtml());
    }

    public function test_a_soft_deleted_record_keeps_its_files_until_deleted_for_good(): void
    {
        $column = $this->addFileField()->column;
        $this->actingAs($this->userWithRole('employee'));

        $file = app(FileStorage::class)->put('%PDF', 'offer.pdf');
        $company = Company::create(['company_name' => 'Acme Corp', $column => [$file->id]]);

        $company->delete();
        $this->assertNotNull(StoredFile::find($file->id));

        $company->forceDelete();
        $this->assertNull(StoredFile::find($file->id));
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $attributes
     */
    protected function addFileField(array $params = [], array $attributes = []): CustomField
    {
        return CustomField::create([
            'model_type' => 'company',
            'name' => 'contract',
            'label' => 'Contract',
            'type' => FieldType::File,
            'params' => $params,
            ...$attributes,
        ]);
    }

    /** Field::fileUrl()'s route, for any column — the tests also ask for the wrong one. */
    protected function fileUrl(Company $company, string $column, StoredFile $file, bool $preview = false): string
    {
        return route('epesi.records.file', array_filter([
            'type' => 'company',
            'id' => $company->id,
            'field' => $column,
            'file' => $file->id,
            'preview' => $preview ? 1 : null,
        ]));
    }
}
