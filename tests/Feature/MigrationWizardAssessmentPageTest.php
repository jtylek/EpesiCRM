<?php

namespace Tests\Feature;

use Epesi\Modules\MigrationWizard\Checks\AssessmentSummary;
use Epesi\Modules\MigrationWizard\Checks\VendorResidue;
use Epesi\Modules\MigrationWizard\Filament\Pages\MigrateLegacy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

require_once __DIR__.'/../Unit/MigrationWizardAssessmentSummaryTest.php';

/** The browser page prints the short summary, not the report. */
class MigrationWizardAssessmentPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_opens_at_once_with_the_version_and_a_scan_button_and_scans_only_when_asked(): void
    {
        Filament::setCurrentPanel('setup');
        $this->app->instance('migration-wizard.legacy_detected', true);

        Livewire::test(MigrateLegacy::class)
            ->assertSee('epesi migration wizard')
            ->assertDontSee('epesi setup')
            ->assertSee('Legacy Epesi data detected:')
            ->assertSee('Version:')
            ->assertSee('Database:')
            ->assertSee('/data directory')
            ->assertSee('Scan my epesi installation')
            ->assertSeeHtml('wire:click="scan"')
            ->assertSeeHtml('wire:target="scan"')
            ->assertSee('Scanning your data')
            ->assertDontSeeHtml('wire:init')
            ->assertDontSee('Assess legacy installation')
            ->assertDontSee('Assessment only')
            ->assertDontSee('make a full backup')
            ->assertDontSee('Scan again')
            ->assertSet('report', null)
            ->assertSet('scanFailed', false);
    }

    public function test_after_the_scan_the_page_shows_the_version_and_the_module_summary(): void
    {
        Filament::setCurrentPanel('setup');
        $this->app->instance('migration-wizard.legacy_detected', true);
        $summary = (new AssessmentSummary)->summarize(\MigrationWizardAssessmentSummaryTest::report());
        $summary['saved_to'] = '/srv/epesi/storage/app/migration-wizard/assessment.json';

        // scan() itself needs the legacy MySQL database; the summary it stores is set here.
        Livewire::test(MigrateLegacy::class)
            ->set('report', $summary)
            ->assertDontSee('Scanning your data')
            ->assertDontSee('Scan my epesi installation')
            ->assertSee('Version: 1.9.1')
            ->assertSee('CRM_Contacts')
            ->assertSee('company 120, contact 300')
            ->assertSee('Premium_Invoice')
            ->assertSee('Custom_Invoice')
            ->assertDontSee('Base_About')
            ->assertSee('Can be migrated (')
            ->assertSee('Will not be migrated (')
            ->assertDontSee('Kept untouched (not migrated)')
            ->assertSee('41 of 60 database tables belong to the modules above')
            ->assertSee('Summary')
            ->assertSee('Start migration')
            ->assertSeeHtml(route('filament.setup.migrate.report', ['format' => 'html']))
            ->assertSeeHtml(route('filament.setup.migrate.report', ['format' => 'txt']))
            ->assertDontSee('/srv/epesi/storage')
            ->assertSeeHtml(route('filament.setup.migrate.progress'))
            ->assertDontSee('company_data_1')
            ->assertDontSee('table_statistics')
            ->assertDontSee('"estimated_rows"');
    }

    public function test_a_failed_scan_shows_a_short_message_and_a_scan_again_link_without_details(): void
    {
        Filament::setCurrentPanel('setup');
        $this->app->instance('migration-wizard.legacy_detected', true);

        // No data/config.php in the test installation: the scan fails and must not leak why.
        Livewire::test(MigrateLegacy::class)
            ->call('scan')
            ->assertSet('scanFailed', true)
            ->assertSee('could not be scanned')
            ->assertSee('Scan again')
            ->assertDontSee('Scanning your data')
            ->assertDontSee('config.php')
            ->assertDontSee('Start migration');
    }

    public function test_a_legacy_only_vendor_folder_blocks_the_scan_with_a_fix_it_warning(): void
    {
        Filament::setCurrentPanel('setup');
        $this->app->instance('migration-wizard.legacy_detected', true);
        $this->app->instance(VendorResidue::class, new class
        {
            /** @return list<string> */
            public function detect(string $root): array
            {
                return ['enyo', 'ezyang'];
            }
        });

        Livewire::test(MigrateLegacy::class)
            ->assertSee('epesi migration wizard')
            ->assertSee('Legacy Epesi data detected:')
            ->assertSee('vendor/ directory is still here')
            ->assertSee('vendor/enyo')
            ->assertSee('vendor/ezyang')
            ->assertSee('Delete the old')
            ->assertSee('Unzip the Epesi 3.0 release package again')
            ->assertDontSee('Scan my epesi installation')
            ->assertDontSee('Scanning your data');
    }

    public function test_without_vendor_residue_the_scan_button_shows_as_before(): void
    {
        Filament::setCurrentPanel('setup');
        $this->app->instance('migration-wizard.legacy_detected', true);
        $this->app->instance(VendorResidue::class, new class
        {
            /** @return list<string> */
            public function detect(string $root): array
            {
                return [];
            }
        });

        Livewire::test(MigrateLegacy::class)
            ->assertSee('Scan my epesi installation')
            ->assertDontSee('vendor/ directory is still here');
    }

    public function test_without_a_legacy_installation_or_in_demo_mode_the_page_is_not_there(): void
    {
        Filament::setCurrentPanel('setup');
        $this->app->instance('migration-wizard.legacy_detected', false);
        Livewire::test(MigrateLegacy::class)->assertStatus(404);

        $this->app->instance('migration-wizard.legacy_detected', true);
        config(['demo.enabled' => true]);
        Livewire::test(MigrateLegacy::class)->assertStatus(404);
    }
}
