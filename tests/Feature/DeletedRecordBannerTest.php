<?php

namespace Tests\Feature;

use App\Enums\RecordPermission;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\EditContact;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ViewContact;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/** A deleted record, opened by a super admin, says so with a Restore button, and cannot be deleted again. */
class DeletedRecordBannerTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_a_deleted_record_shows_a_banner_with_restore_and_no_delete(): void
    {
        Filament::setCurrentPanel('main');
        $this->actingAs($this->userWithRole('super_admin'));
        $record = Contact::create(['first_name' => 'Some', 'last_name' => 'One', 'permission' => RecordPermission::Public]);
        $record->delete();

        $view = Livewire::test(ViewContact::class, ['record' => $record->getKey()])
            ->assertSee('Record was deleted')
            ->assertActionHidden('delete');

        Livewire::test(EditContact::class, ['record' => $record->getKey()])
            ->assertActionHidden('delete');

        $view->callAction('restore');

        $this->assertFalse($record->fresh()->trashed());
        $view->assertDontSee('Record was deleted');
    }

    public function test_a_live_record_has_no_banner(): void
    {
        Filament::setCurrentPanel('main');
        $this->actingAs($this->userWithRole('super_admin'));
        $record = Contact::create(['first_name' => 'Some', 'last_name' => 'One', 'permission' => RecordPermission::Public]);

        Livewire::test(ViewContact::class, ['record' => $record->getKey()])
            ->assertDontSee('Record was deleted');
    }
}
