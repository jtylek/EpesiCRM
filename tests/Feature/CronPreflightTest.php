<?php

namespace Tests\Feature;

use App\Services\Setup\Requirements;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * cron.php runs with the command line's PHP, which can be another binary than the web server's
 * and lack an extension. It says so (bootstrap/preflight.php) and Server Check shows it.
 */
class CronPreflightTest extends TestCase
{
    private string $note;

    protected function setUp(): void
    {
        parent::setUp();

        $this->note = storage_path('framework/cron-preflight.json');
        File::delete($this->note);
    }

    protected function tearDown(): void
    {
        File::delete($this->note);

        parent::tearDown();
    }

    public function test_server_check_is_clean_without_a_note(): void
    {
        $this->assertNull(Requirements::cronNote());
        $this->assertTrue(collect((new Requirements)->check(phpIni: false))->doesntContain('label', 'PHP that runs cron'));
    }

    public function test_a_note_from_a_failed_cron_start_fails_the_requirements(): void
    {
        File::put($this->note, json_encode([
            'time' => '2026-01-02 03:04:05',
            'php' => '8.5.1',
            'binary' => '/opt/alt/php85/usr/bin/php',
            'problems' => ['PHP extension mbstring: missing'],
        ]));

        $row = collect((new Requirements)->check(phpIni: false))->firstWhere('label', 'PHP that runs cron');

        $this->assertNotNull($row);
        $this->assertFalse($row['ok']);
        $this->assertTrue($row['required']);
        $this->assertStringContainsString('/opt/alt/php85/usr/bin/php', $row['status']);
        $this->assertStringContainsString('PHP extension mbstring: missing', $row['status']);
        $this->assertFalse(Requirements::passes((new Requirements)->check(phpIni: false)));
    }

    public function test_an_unreadable_note_is_ignored(): void
    {
        File::put($this->note, '{not json');

        $this->assertNull(Requirements::cronNote());
    }

    public function test_cron_php_without_the_extensions_says_so_and_leaves_a_note(): void
    {
        // -n: no php.ini, so none of the extensions epesi needs is loaded.
        exec(escapeshellarg(PHP_BINARY).' -n '.escapeshellarg(base_path('cron.php')).' 2>&1', $output, $exit);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('epesi cannot run with this PHP', implode("\n", $output));
        $this->assertStringContainsString('PHP extension mbstring: missing', implode("\n", $output));

        $note = Requirements::cronNote();

        $this->assertNotNull($note);
        $this->assertSame(PHP_BINARY, $note['binary']);
        $this->assertContains('PHP extension mbstring: missing', $note['problems']);
    }

    public function test_a_clean_cron_start_takes_the_note_away(): void
    {
        File::put($this->note, json_encode(['time' => 'x', 'php' => 'x', 'binary' => 'x', 'problems' => ['x: missing']]));

        // The same extensions this test process has, so the preflight passes (cron.php then
        // goes on to run epesi:cron, which is not wanted here: run the preflight alone).
        exec(escapeshellarg(PHP_BINARY).' '.$this->iniArguments().' -r '.escapeshellarg('require '.var_export(base_path('bootstrap/preflight.php'), true).';').' 2>&1', $output, $exit);

        $this->assertSame(0, $exit, implode("\n", $output));
        $this->assertFileDoesNotExist($this->note);
    }

    /** The ini this test process runs with, so a child PHP has the same extensions. */
    private function iniArguments(): string
    {
        $ini = php_ini_loaded_file();

        return ($ini ? '-c '.escapeshellarg($ini) : '').' '.implode(' ', array_map(
            fn (string $extension): string => '-d extension='.escapeshellarg($extension),
            array_filter(['pdo_sqlite', 'sqlite3'], 'extension_loaded'),
        ));
    }
}
