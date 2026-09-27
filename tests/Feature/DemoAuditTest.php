<?php

namespace Tests\Feature;

use App\Models\LoginAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * `php artisan demo:audit --raw`: every column of every session, for the
 * demo-visitors tooling (a separate machine) to merge and dedupe by id — see
 * demo-visitors/login_audit_analysis.md. Unlike --csv (a curated, localized
 * subset for a person), --raw is UTC and unformatted, on purpose.
 *
 * Both --csv and --raw write straight to the `php://output` stream rather
 * than through Laravel's testable Artisan::output() buffer (so a person can
 * redirect it: `demo:audit --csv > file.csv`), so a test has to capture it
 * the same way a shell redirect would.
 */
class DemoAuditTest extends TestCase
{
    use RefreshDatabase;

    private function callCapturingOutput(array $arguments): string
    {
        ob_start();
        Artisan::call('demo:audit', $arguments);

        return ob_get_clean();
    }

    public function test_raw_dumps_every_column_in_utc(): void
    {
        $user = User::factory()->create(['email' => 'visitor@example.com']);
        LoginAudit::create([
            'user_id' => $user->id,
            'login' => 'visitor@example.com',
            'impersonated_by' => null,
            'started_at' => '2026-09-26 20:27:00',
            'ended_at' => '2026-09-26 20:28:00',
            'ip_address' => '87.119.179.250',
            'host_name' => '87-119-179-250.ip.elisa.ee',
            'device' => 'Linux · Firefox',
        ]);

        $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", $this->callCapturingOutput(['--raw' => true, '--days' => 365])))));

        $this->assertSame(
            ['id', 'user_id', 'login', 'impersonated_by', 'started_at', 'ended_at', 'ip_address', 'host_name', 'device'],
            str_getcsv($lines[0]),
        );
        $this->assertSame(
            ['visitor@example.com', '', '2026-09-26 20:27:00', '2026-09-26 20:28:00', '87.119.179.250', '87-119-179-250.ip.elisa.ee', 'Linux · Firefox'],
            array_slice(str_getcsv($lines[1]), 2),
        );
    }

    public function test_raw_ignores_sessions_older_than_days(): void
    {
        LoginAudit::create([
            'login' => 'old@example.com',
            'started_at' => now()->subDays(40),
            'ended_at' => now()->subDays(40),
            'ip_address' => '1.2.3.4',
        ]);

        $output = $this->callCapturingOutput(['--raw' => true, '--days' => 30]);

        $this->assertStringNotContainsString('old@example.com', $output);
    }
}
