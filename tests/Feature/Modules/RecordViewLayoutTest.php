<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages\ViewContact;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * A record's View page reads down the first column and then down the second
 * (RecordsetResource::flowIntoColumns), with a full-width field like Memo
 * standing between the runs.
 */
class RecordViewLayoutTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_a_view_page_flows_its_fields_down_columns_around_full_width_ones(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $contact = Contact::create(['last_name' => 'Tylek', 'first_name' => 'Janusz', 'permission' => RecordPermission::Public]);

        $html = $this->get(ViewContact::getUrl(['record' => $contact]))->assertOk()->getContent();

        // 11 ordinary fields make a run of 6 rows; the Address section's 6 make 3.
        preg_match_all('/class="rb-column-flow" style="--rb-rows: (\d+)"/', $html, $runs);
        $this->assertSame(['6', '3'], $runs[1]);

        // Memo spans both columns, so it sits after the first run, not in it.
        $firstRun = strpos($html, 'rb-column-flow');
        $this->assertGreaterThan(strpos($html, 'Permission', $firstRun), strpos($html, 'Memo', $firstRun));
        $this->assertLessThan(strpos($html, 'Address 1', $firstRun), strpos($html, 'Memo', $firstRun));
    }
}
