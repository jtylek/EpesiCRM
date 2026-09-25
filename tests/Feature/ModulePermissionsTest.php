<?php

namespace Tests\Feature;

use App\Filament\Administration\Resources\Modules\Pages\ViewModule;
use App\Models\Module;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Administration → Modules → Generate permissions runs `shield:generate`
 * inside the request. The command itself is stood in for: run for real it
 * writes its policy files into this checkout, so a regression would overwrite
 * the modules' ownership policies in the working tree rather than only fail.
 */
class ModulePermissionsTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    public function test_it_generates_permissions_only_and_stays_on_the_administration_panel(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));

        $module = Module::create([
            'module_id' => 'Epesi/Attachments',
            'name' => 'Attachments',
            'version' => '1.0.0',
            'path' => 'Epesi/Attachments',
            'namespace' => 'Epesi\\Modules\\Attachments\\',
            'plugin_class' => 'Epesi\\Modules\\Attachments\\AttachmentsPlugin',
            'panels' => ['main'],
            'manifest' => [],
            'enabled' => true,
        ]);

        Artisan::shouldReceive('call')
            ->once()
            ->withArgs(fn (string $command, array $parameters): bool => $command === 'shield:generate'
                && $parameters['--panel'] === 'main'
                && $parameters['--option'] === 'permissions')
            // What the real command does: it makes the panel it generates for
            // the current one, and leaves it that way.
            ->andReturnUsing(function (): int {
                Filament::setCurrentPanel('main');

                return 0;
            });

        Filament::setCurrentPanel('administration');

        Livewire::test(ViewModule::class, ['record' => $module->getKey()])
            ->callAction('generatePermissions')
            ->assertNotified('Permissions generated');

        $this->assertSame('administration', Filament::getCurrentPanel()->getId());
    }
}
