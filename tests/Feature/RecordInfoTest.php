<?php

namespace Tests\Feature;

use App\Enums\RecordPermission;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Filament\Schemas\RecordInfoEntries;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/** The Record Info modal on a RecordBrowser View page: label | who | when. */
class RecordInfoTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_record_info_shows_recordset_id_and_who_last_name_first(): void
    {
        Filament::setCurrentPanel('main');
        $user = $this->userWithRole('manager');
        Contact::create(['first_name' => 'Me', 'last_name' => 'Myself', 'user_id' => $user->id]);
        $this->actingAs($user);

        $record = Contact::create(['first_name' => 'Some', 'last_name' => 'One', 'permission' => RecordPermission::Public]);

        $component = RecordInfoEntries::components('contacts')[0];
        $viewData = (fn () => ($this->viewData[0])($record))->call($component);

        $html = view('filament.components.record-info', $viewData)->render();

        $this->assertStringContainsString('contacts/'.$record->getKey(), $html);
        $this->assertStringContainsString('Myself Me', $html);
        // Then Watchdog's rows: the author watches the record they created.
        $this->assertSame(['Updated', 'Created', 'Watching'], array_column($viewData['rows'], 0));
    }
}
