<?php

namespace Tests\Feature;

use App\Models\LoginAudit;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\DemoReset;
use App\Services\FileStorage;
use App\Support\Setup\SetupState;
use Epesi\Modules\CRM\Companies\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `php artisan demo:reset`: the demo back to freshly installed with the demo
 * data, every night, keeping the login audit. The service is tested directly:
 * the command around it takes the site into maintenance mode, which in a test
 * would be this checkout's own.
 */
class DemoResetTest extends TestCase
{
    use RefreshDatabase;

    protected string $scratch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scratch = storage_path('framework/testing/demo-'.uniqid());
        File::ensureDirectoryExists($this->scratch);
        File::put($this->scratch.'/.env', "APP_NAME=epesi\n");
        $this->app->useEnvironmentPath($this->scratch);
        config(['setup.marker_path' => $this->scratch.'/installed.json', 'setup.code_path' => $this->scratch.'/setup-code.txt', 'demo.enabled' => true]);

        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->scratch);

        parent::tearDown();
    }

    protected function audit(User $user, string $ip): LoginAudit
    {
        return LoginAudit::create(['user_id' => $user->id, 'login' => $user->email, 'started_at' => now(), 'ended_at' => now(), 'ip_address' => $ip]);
    }

    public function test_it_refuses_to_run_outside_demo_mode(): void
    {
        config(['demo.enabled' => false]);
        Company::create(['company_name' => 'Real customer']);

        $this->artisan('demo:reset', ['--force' => true])->assertFailed();

        $this->assertSame(1, Company::query()->withoutGlobalScopes()->count());
    }

    public function test_the_demo_goes_back_to_the_demo_data_and_keeps_the_login_audit(): void
    {
        $reset = app(DemoReset::class);
        $reset->run();

        $employee = User::query()->where('email', 'employee@example.com')->sole();
        $this->assertTrue(SetupState::isInstalled());
        $this->assertFalse(SetupState::finishPending(), 'no setup pages for the demo');

        // A day of visitors: a login, a company, a file, an account.
        $visitor = User::create(['name' => 'Visitor', 'email' => 'visitor@example.test', 'password' => 'secret123']);
        $kept = $this->audit($employee, '203.0.113.7');
        $gone = $this->audit($visitor, '198.51.100.2');
        Company::create(['company_name' => 'Visitor Ltd']);
        FileStorage::disk()->put('ab/cd/upload.bin', 'x');

        $this->assertSame(['login_audits' => 2], $reset->run());

        $this->assertFalse(Company::query()->withoutGlobalScopes()->where('company_name', 'Visitor Ltd')->exists());
        $this->assertTrue(Company::query()->withoutGlobalScopes()->where('company_name', 'Acme Corp')->exists(), 'the demo data is back');
        $this->assertFalse(User::query()->where('email', 'visitor@example.test')->exists());
        $this->assertFalse(FileStorage::disk()->exists('ab/cd/upload.bin'), 'the visitor\'s upload is gone');
        $this->assertTrue(StoredFile::query()->where('name', 'like', 'demo-note-%')->exists(), 'the demo notes have their files');
        StoredFile::query()->where('name', 'like', 'demo-note-%')->get()
            ->each(fn (StoredFile $file) => $this->assertTrue($file->isOnDisk(), "{$file->name} is on disk"));

        $this->assertSame('203.0.113.7', LoginAudit::query()->findOrFail($kept->id)->ip_address, 'same id');
        $this->assertSame($employee->id, LoginAudit::query()->findOrFail($kept->id)->user_id, 'the demo users come back with the same ids');

        $orphan = LoginAudit::query()->findOrFail($gone->id);
        $this->assertNull($orphan->user_id);
        $this->assertSame('visitor@example.test', $orphan->login);

        $this->assertFalse(DemoReset::interrupted());
    }

    public function test_a_reset_that_stopped_halfway_continues_from_the_saved_audit(): void
    {
        $reset = app(DemoReset::class);
        $reset->run();
        $audit = $this->audit(User::query()->where('email', 'manager@example.com')->sole(), '203.0.113.9');

        // As if the last reset saved the audit, then died after the wipe.
        Storage::disk('local')->put('demo/pending', $reset->saveKeptTables());
        DB::table('login_audits')->delete();
        $this->assertTrue(DemoReset::interrupted());

        $reset->run();

        $this->assertSame('203.0.113.9', LoginAudit::query()->findOrFail($audit->id)->ip_address);
        $this->assertFalse(DemoReset::interrupted());
    }
}
