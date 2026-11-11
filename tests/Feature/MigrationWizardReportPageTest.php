<?php

namespace Tests\Feature;

use Epesi\Modules\MigrationWizard\Report\MigrationReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Administration → Migration Report: the report and migration_log.txt of a finished migration stay reachable
 * after the migration page itself is gone.
 */
class MigrationWizardReportPageTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanFiles();
        parent::tearDown();
    }

    private function cleanFiles(): void
    {
        @unlink(MigrationReport::path());
        @unlink(MigrationReport::logPath());
    }

    private function writeFiles(): void
    {
        if (! is_dir(dirname(MigrationReport::path()))) {
            mkdir(dirname(MigrationReport::path()), 0775, true);
        }
        file_put_contents(MigrationReport::path(), '<html><body>report</body></html>');
        file_put_contents(MigrationReport::logPath(), "EPESI MIGRATION LOG\n");
    }

    public function test_no_page_and_no_files_until_a_migration_wrote_them(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));

        $this->get('/administration/migration-report')->assertForbidden();
        $this->get('/administration/migration-report/html')->assertNotFound();
        $this->get('/administration/migration-report/txt')->assertNotFound();
    }

    public function test_a_super_admin_opens_the_page_the_report_and_the_log_and_downloads_the_log(): void
    {
        $this->writeFiles();
        $this->actingAs($this->userWithRole('super_admin'));

        $this->get('/administration/migration-report')->assertOk()->assertSee('Open the migration report in a new window');
        $html = $this->get('/administration/migration-report/html')->assertOk();
        $this->assertStringContainsString('report', (string) file_get_contents($html->baseResponse->getFile()->getPathname()));
        $this->get('/administration/migration-report/txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->get('/administration/migration-report/txt?download=1')->assertOk()->assertDownload('migration_log.txt');
    }

    public function test_other_roles_and_guests_cannot_reach_the_files(): void
    {
        $this->writeFiles();

        $this->get('/administration/migration-report/txt')->assertRedirect();

        $this->actingAs($this->userWithRole('employee'));
        $this->get('/administration/migration-report/txt')->assertForbidden();
    }
}
