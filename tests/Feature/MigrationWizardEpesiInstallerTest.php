<?php

use App\Services\Modules\ModuleInstaller;
use App\Services\Setup\InstallOptions;
use App\Services\Setup\ModulePlan;
use App\Services\Setup\SetupException;
use App\Support\Setup\SetupState;
use Epesi\Modules\MigrationWizard\Install\EpesiInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\TestCase;

final class MigrationWizardEpesiInstallerTest extends TestCase
{
    use RefreshDatabase;

    private function installer(): MockInterface
    {
        return Mockery::mock(EpesiInstaller::class, [app(ModuleInstaller::class), app(ModulePlan::class)])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
    }

    public function test_it_runs_migrate_roles_and_modules_in_order_once_the_namespace_is_free(): void
    {
        Schema::create('legacy_probe_a', static fn ($table) => $table->id());

        $installer = $this->installer();
        $installer->shouldReceive('migrate')->once()->ordered();
        $installer->shouldReceive('seedRoles')->once()->ordered();
        $installer->shouldReceive('installModules')->once()->ordered()->with(['Epesi/Roundcube'])->andReturn(['epesi/core', 'epesi/roundcube']);

        self::assertSame(
            ['modules' => ['epesi/core', 'epesi/roundcube']],
            $installer->prepare(['probe_a' => 'legacy_probe_a'], ['Epesi/Roundcube']),
        );
        // No administrator and no installed marker: setup-only access lasts until verification.
        self::assertFalse(SetupState::isInstalled());
    }

    public function test_a_rerun_after_an_interrupted_install_is_allowed(): void
    {
        // The freed name now holds an Epesi 3 table, the legacy one sits under its new name.
        Schema::create('probe_a', static fn ($table) => $table->id());
        Schema::create('legacy_probe_a', static fn ($table) => $table->id());

        $installer = $this->installer();
        $installer->shouldReceive('migrate')->once();
        $installer->shouldReceive('seedRoles')->once();
        $installer->shouldReceive('installModules')->once()->andReturn([]);

        self::assertSame(['modules' => []], $installer->prepare(['probe_a' => 'legacy_probe_a']));
    }

    public function test_it_refuses_while_a_legacy_table_still_occupies_the_epesi_3_namespace(): void
    {
        Schema::create('probe_b', static fn ($table) => $table->id());

        $installer = $this->installer();
        $installer->shouldReceive('migrate')->never();

        $this->expectException(SetupException::class);
        $this->expectExceptionMessage('1 still in the Epesi 3 namespace');
        $installer->prepare(['probe_b' => 'legacy_probe_b']);
    }

    public function test_it_refuses_when_a_legacy_table_has_gone_missing_or_no_map_is_given(): void
    {
        $installer = $this->installer();
        $installer->shouldReceive('migrate')->never();

        try {
            $installer->prepare(['probe_c' => 'legacy_probe_c']);
            self::fail('missing legacy table was accepted');
        } catch (SetupException $e) {
            self::assertStringContainsString('1 missing', $e->getMessage());
        }

        $this->expectException(SetupException::class);
        $installer->prepare([]);
    }

    public function test_the_regular_install_entry_point_is_disabled(): void
    {
        $this->expectException(SetupException::class);
        app(EpesiInstaller::class)->install(new InstallOptions(adminName: 'A', adminEmail: 'a@example.test', adminPassword: 'secret-pass-123'));
    }
}
