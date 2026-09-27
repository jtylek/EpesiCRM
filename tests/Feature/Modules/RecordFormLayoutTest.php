<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\EditContact;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The Create/Edit form flows its fields down columns the same way the View
 * page does (RecordsetResource::flowIntoColumns). Before this, the form
 * schema skipped that flow and let Filament lay fields out row by row, so a
 * record's second field (e.g. First Name) sat beside its first (Last Name)
 * instead of below it — a different reading order than View.
 */
class RecordFormLayoutTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_an_edit_page_flows_its_fields_down_columns_around_full_width_ones(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $contact = Contact::create(['last_name' => 'Tylek', 'first_name' => 'Janusz', 'permission' => RecordPermission::Public]);

        $html = $this->get(EditContact::getUrl(['record' => $contact]))->assertOk()->getContent();

        // The leading section's 12 fields (View's 11 plus the form-only Fax)
        // make a run of 6 rows; Address's 6 make 3; the form-only Home Address
        // section's 7 (Home Phone plus its own address block) make 4; the
        // form-only Login section's 1 makes 1.
        preg_match_all('/class="rb-column-flow" style="--rb-rows: (\d+)"/', $html, $runs);
        $this->assertSame(['6', '3', '4', '1'], $runs[1]);
    }
}
