<?php

namespace Tests\Feature;

use App\Filament\Administration\Resources\LoginAudits\Pages\ListLoginAudits;
use App\Models\LoginAudit;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/** Administration → Login Audits: the toolbar's "All users" select. */
class LoginAuditUserFilterTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_the_toolbar_select_narrows_the_list_to_one_user(): void
    {
        $admin = $this->userWithRole('super_admin', ['name' => 'Admin']);
        $ann = $this->userWithRole('employee', ['name' => 'Ann']);

        $this->actingAs($admin);
        Filament::setCurrentPanel('administration');

        $now = now();
        $mine = LoginAudit::create(['user_id' => $admin->id, 'login' => 'admin', 'started_at' => $now, 'ended_at' => $now, 'ip_address' => '127.0.0.1']);
        $hers = LoginAudit::create(['user_id' => $ann->id, 'login' => 'ann', 'started_at' => $now, 'ended_at' => $now, 'ip_address' => '127.0.0.1']);

        Livewire::test(ListLoginAudits::class)
            ->assertSee('All users')
            ->assertCanSeeTableRecords([$mine, $hers])
            ->set('auditUser', (string) $ann->id)
            ->assertCanSeeTableRecords([$hers])
            ->assertCanNotSeeTableRecords([$mine]);
    }
}
