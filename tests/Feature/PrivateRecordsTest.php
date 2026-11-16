<?php

namespace Tests\Feature;

use App\Enums\RecordPermission;
use App\Services\FileStorage;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\AttachmentResource;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\ListAttachments;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\CompanyResource;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages\ListCompanies;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\RecordBrowser\Access\PrivateRecords;
use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\RecordsetRecords;
use Epesi\Modules\RecordBrowser\Filament\Administration\RecordInAdministration;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * A Private record is its author's alone: a manager or a super admin doesn't
 * see it either, in the main panel or the Administration panel, through a
 * list, a page, a file route or a policy. Only the model's own "yours"
 * clauses (your own company, ...) still reach it, and with nobody signed in
 * (console, queue, importers) nothing is hidden.
 */
class PrivateRecordsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    /** @return array<string, array{0: class-string<Model>, 1: array<string, mixed>}> */
    public static function recordsets(): array
    {
        return [
            'attachment' => [Attachment::class, ['title' => 'A colleague\'s note']],
            'company' => [Company::class, ['company_name' => 'Secret Co']],
            'contact' => [Contact::class, ['first_name' => 'Sam', 'last_name' => 'Secret']],
            'task' => [Task::class, ['title' => 'Secret task']],
            'meeting' => [Meeting::class, ['title' => 'Secret meeting', 'date' => '2026-09-24', 'time' => '09:30:00', 'duration_minutes' => 60]],
            'phone_call' => [PhoneCall::class, ['subject' => 'Secret call', 'called_at' => '2026-09-27 10:00:00', 'other_customer' => true, 'other_customer_name' => 'X']],
        ];
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('recordsets')]
    public function test_nobody_but_its_author_sees_a_private_record(string $model, array $attributes): void
    {
        $author = $this->userWithRole('employee');
        $this->actingAs($author);
        $private = $model::create([...$attributes, 'permission' => RecordPermission::Private]);
        $public = $model::create([...$attributes, 'permission' => RecordPermission::Public]);

        $visible = fn (Model $record): bool => $model::query()->whereKey($record->getKey())->exists();

        $this->assertTrue($visible($private), 'its author sees it');

        foreach (['super_admin', 'manager', 'employee'] as $role) {
            $this->actingAs($this->userWithRole($role));

            foreach ([null, 'main', 'administration'] as $panel) {
                Filament::setCurrentPanel($panel);

                $this->assertFalse($visible($private), "a {$role} in panel ".($panel ?? 'none'));
                $this->assertTrue($visible($public), "a {$role} in panel ".($panel ?? 'none'));
            }
        }

        // Nobody signed in: the console, a queued job, an importer.
        auth()->logout();
        $this->assertTrue($visible($private));
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('recordsets')]
    public function test_a_manager_may_not_view_change_or_delete_someone_elses_private_record(string $model, array $attributes): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $private = $model::create([...$attributes, 'permission' => RecordPermission::Private]);
        $public = $model::create([...$attributes, 'permission' => RecordPermission::Public]);

        $manager = $this->userWithRole('manager');
        $this->actingAs($manager);
        // Loaded past the scope, as a route or a background job might.
        $private = $model::query()->withoutGlobalScopes()->findOrFail($private->getKey());

        foreach (['view', 'update', 'delete', 'restore'] as $ability) {
            $this->assertFalse($manager->can($ability, $private), "{$ability} someone else's private record");
            $this->assertTrue($manager->can($ability, $public), "{$ability} a public one");
        }

        $this->assertFalse(PrivateRecords::reachableBy($this->userWithRole('super_admin'), $private));
    }

    public function test_your_own_company_stays_yours_even_when_someone_else_made_it_private(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $company = Company::create(['company_name' => 'Our Company', 'permission' => RecordPermission::Private]);

        $manager = $this->userWithRole('manager');
        Contact::create(['first_name' => 'Mia', 'last_name' => 'Manager', 'company_id' => $company->id, 'user_id' => $manager->id]);
        $manager = $manager->fresh();

        $this->actingAs($manager);
        $this->assertTrue(Company::query()->whereKey($company->id)->exists());
        $this->assertTrue($manager->can('update', $company));
    }

    public function test_a_super_admin_cannot_open_someone_elses_private_note_in_the_main_panel(): void
    {
        $author = $this->userWithRole('employee');
        $this->actingAs($author);
        $contact = Contact::create(['first_name' => 'Ann', 'last_name' => 'Smith']);
        $file = app(FileStorage::class)->put('%PDF secret', 'secret.pdf');
        $note = Attachment::create(['title' => 'Private', 'note' => 'Must remain hidden', 'permission' => RecordPermission::Private, 'files' => [$file->id]]);
        $note->attachTo($contact);
        $shared = URL::temporarySignedRoute('epesi.attachments.shared', now()->addWeek(), ['attachment' => $note->id, 'file' => $file->id]);

        $this->actingAs($this->userWithRole('super_admin'));

        $onContact = ['record' => $note, 'attachable_type' => 'contact', 'attachable_id' => $contact->id];
        $this->get(AttachmentResource::getUrl('view', $onContact, panel: 'main'))->assertForbidden()->assertSee('have permissions to access this record');
        $this->get(AttachmentResource::getUrl('edit', $onContact, panel: 'main'))->assertForbidden()->assertSee('have permissions to access this record');
        $this->get(route('epesi.attachments.download', ['attachment' => $note->id, 'file' => $file->id]))->assertForbidden();
        $this->assertSame(0, $contact->attachments()->count(), 'nor on the Notes tab of its record');

        Filament::setCurrentPanel('main');
        Livewire::test(ListAttachments::class)
            ->removeTableFilter('edited_by')
            ->assertDontSee('Must remain hidden');

        // A "Get link" URL is for whoever holds it, signed in or not.
        $this->get($shared)->assertOk()->assertHeader('Content-Disposition');
        $this->assertStringContainsString('secret.pdf', $this->get($shared)->headers->get('Content-Disposition'));
    }

    public function test_the_administration_panel_does_not_show_someone_elses_private_record_either(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $private = Company::create(['company_name' => 'Secret Co', 'permission' => RecordPermission::Private]);
        $public = Company::create(['company_name' => 'Open Co', 'permission' => RecordPermission::Public]);

        $admin = $this->userWithRole('super_admin');
        $this->actingAs($admin);

        $this->get(CompanyResource::getUrl('view', ['record' => $private], panel: 'main'))->assertForbidden()->assertSee('have permissions to access this record');
        $this->get(CompanyResource::getUrl('edit', ['record' => $private], panel: 'main'))->assertForbidden()->assertSee('have permissions to access this record');
        $this->get(RecordInAdministration::url('company', $private))->assertForbidden();
        $this->get(RecordInAdministration::url('company', $public))->assertOk();

        Filament::setCurrentPanel('main');
        Livewire::test(ListCompanies::class)
            ->removeTableFilter('my_records')
            ->assertCanSeeTableRecords([$public])
            ->assertCanNotSeeTableRecords([$private]);

        Filament::setCurrentPanel('administration');
        Livewire::test(RecordsetRecords::class, ['alias' => 'company'])
            ->filterTable(RecordsetRecords::DELETED_FILTER, 'all')
            ->assertCanSeeTableRecords([$public])
            ->assertCanNotSeeTableRecords([$private]);
    }
}
