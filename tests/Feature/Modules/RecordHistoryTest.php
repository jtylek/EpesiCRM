<?php

namespace Tests\Feature\Modules;

use App\Enums\RecordPermission;
use App\Enums\RecordPriority;
use App\Enums\RecordStatus;
use App\Models\User;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\Pages\ViewPhoneCall;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\RecordBrowser\Filament\RelationManagers\HistoryRelationManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The History addon reads like the record: field labels and the values the
 * View page shows, not columns and what they store — Epesi's edit history.
 */
class RecordHistoryTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_changes_show_labels_and_values_not_columns_and_keys(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        Filament::setCurrentPanel(Filament::getPanel('main'));

        $ann = Contact::create(['first_name' => 'Ann', 'last_name' => 'Buyer', 'permission' => RecordPermission::Public]);
        $call = PhoneCall::create([
            'subject' => 'Offer',
            'called_at' => '2026-10-01 10:00:00',
            'contact_id' => $ann->id,
            'status' => RecordStatus::Open,
            'priority' => RecordPriority::Medium,
            'permission' => RecordPermission::Public,
        ]);
        $call->update([
            'status' => RecordStatus::InProgress,
            'priority' => RecordPriority::Low,
            'permission' => RecordPermission::PublicReadOnly,
        ]);

        Livewire::test(HistoryRelationManager::class, ['ownerRecord' => $call, 'pageClass' => ViewPhoneCall::class])
            ->assertSeeText('Status: Open → In Progress')
            ->assertSeeText('Priority: Medium → Low')
            ->assertSeeText('Permission: Public → Public, Read-Only')
            ->assertDontSeeText('status: 0 → 1')
            // Marked like a diff: the old value red, the new one green.
            ->assertSeeHtml('Status: <span class="epesi-history-old">Open</span> → <span class="epesi-history-new">In Progress</span>')
            // Created: the new values alone, no red "-" before each.
            ->assertSeeHtml('Contact: <span class="epesi-history-new">Ann Buyer</span>')
            ->assertSeeHtml('Other Customer (not in system): <span class="epesi-history-new">No</span>')
            ->assertSeeHtml('Subject: <span class="epesi-history-new">Offer</span>')
            ->assertDontSeeText('Subject: - →');
    }

    public function test_history_imported_under_class_names_is_found_again(): void
    {
        $user = $this->userWithRole('employee');
        $this->actingAs($user);

        $call = PhoneCall::create(['subject' => 'Offer', 'called_at' => '2026-10-01 10:00:00', 'permission' => RecordPermission::Public]);
        $call->update(['subject' => 'Offer sent']);

        // As import:legacy wrote it before it used the aliases.
        DB::table('activity_log')->update(['subject_type' => PhoneCall::class, 'causer_type' => User::class]);
        $this->assertSame(0, $call->activities()->count());

        (require base_path('database/migrations/2026_09_27_030000_map_module_morph_types_to_aliases.php'))->up();

        $this->assertSame(2, $call->activities()->count());
        $this->assertTrue($call->activities()->first()->causer->is($user));
    }
}
