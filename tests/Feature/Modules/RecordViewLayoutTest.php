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
 * (RecordsetResource::flowIntoColumns), in exactly `fields()` order —
 * there's no forced grouping, but ContactResource follows the
 * engine's convention of declaring short fields first, a Memo next, then its
 * Collection fields (E-mail addresses, Phone numbers, …) last, so that's the
 * order this test sees.
 */
class RecordViewLayoutTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_a_view_page_flows_its_fields_down_columns_around_full_width_ones(): void
    {
        $this->actingAs($this->userWithRole('employee'));

        $contact = Contact::create(['last_name' => 'Tylek', 'first_name' => 'Janusz', 'permission' => RecordPermission::Public]);

        $html = $this->get(ViewContact::getUrl(['record' => $contact]))->assertOk()->getContent();

        // 7 short fields (Last Name, First Name, Title, Group, Company,
        // Related Companies, Permission) make one 2-column run of 4 rows;
        // Memo and the Collection fields are full-width, so they fall
        // outside it — it's the only rb-column-flow grid on the page.
        preg_match_all('/class="rb-column-flow" style="--rb-rows: (\d+)"/', $html, $runs);
        $this->assertSame(['4'], $runs[1]);

        // Memo sits after the short-field run.
        $firstRun = strpos($html, 'rb-column-flow');
        $this->assertGreaterThan(strpos($html, 'Permission', $firstRun), strpos($html, 'Memo', $firstRun));
        // The Addresses entry's label, not Mail's MailAddressesRelationManager.
        // Collections come after Memo, per ContactResource's own field order.
        preg_match('/>\s*Addresses\s*</', $html, $addresses, PREG_OFFSET_CAPTURE, $firstRun);
        $this->assertLessThan($addresses[0][1], strpos($html, 'Memo', $firstRun));
    }

    public function test_the_view_page_kebab_offers_delete(): void
    {
        $this->actingAs($this->userWithRole('manager'));
        $contact = Contact::create(['last_name' => 'Tylek', 'first_name' => 'Janusz', 'permission' => RecordPermission::Public]);

        $this->get(ViewContact::getUrl(['record' => $contact]))
            ->assertOk()
            ->assertSee('Delete');
    }
}
