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
 * page does (RecordsetResource::flowIntoColumns), in exactly `fields()`
 * order. Before this, the form schema skipped that flow and let Filament lay
 * fields out row by row, so a record's second field (e.g. First Name) sat
 * beside its first (Last Name) instead of below it — a different reading
 * order than View.
 */
class RecordFormLayoutTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_an_edit_page_flows_its_fields_down_columns_around_full_width_ones(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $contact = Contact::create(['last_name' => 'Tylek', 'first_name' => 'Janusz', 'permission' => RecordPermission::Public]);

        $html = $this->get(EditContact::getUrl(['record' => $contact]))->assertOk()->getContent();

        // 7 short fields (Last Name, First Name, Title, Group, Company,
        // Related Companies, Permission) make one 2-column run of 4 rows;
        // Memo and the Collection fields are full-width, so they fall
        // outside it — it's the only rb-column-flow grid on the page.
        preg_match_all('/class="rb-column-flow" style="--rb-rows: (\d+)"/', $html, $runs);
        $this->assertSame(['4'], $runs[1]);
    }
}
