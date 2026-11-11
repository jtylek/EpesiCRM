<?php

use Epesi\Modules\MigrationWizard\State\MigrationState;
use PHPUnit\Framework\TestCase;

final class MigrationWizardMigrationStateTransitionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/epesi-state-transition-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/{*,.state-*}', GLOB_BRACE) ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_the_full_forward_ladder_is_accepted_one_step_at_a_time(): void
    {
        $state = new MigrationState($this->dir);
        $version = 0;
        foreach (['assessed', 'backed_up', 'frozen', 'tables_namespaced', 'epesi_installed', 'importing', 'verified', 'cleaned', 'complete'] as $phase) {
            $version = $state->update($version, ['phase' => $phase])['version'];
        }
        self::assertSame('complete', $state->read()['phase']);
    }

    public function test_skipping_a_phase_is_refused(): void
    {
        $state = new MigrationState($this->dir);
        $state->update(0, ['phase' => 'backed_up']);

        foreach (['tables_namespaced', 'importing', 'verified', 'cleaned', 'complete', 'failed', 'unknown'] as $phase) {
            try {
                $state->update(1, ['phase' => $phase]);
                self::fail("backed_up -> {$phase} was accepted.");
            } catch (RuntimeException) {
            }
        }
        self::assertSame('backed_up', $state->read()['phase']);
    }

    public function test_a_final_phase_is_immutable_and_cleanup_cannot_be_reentered(): void
    {
        $state = new MigrationState($this->dir);
        $version = 0;
        foreach (['backed_up', 'frozen', 'tables_namespaced', 'epesi_installed', 'importing', 'verified', 'cleaned'] as $phase) {
            $version = $state->update($version, ['phase' => $phase])['version'];
        }
        // Going back is refused once cleaned.
        foreach (['verified', 'importing', 'backed_up'] as $phase) {
            try {
                $state->update($version, ['phase' => $phase]);
                self::fail("cleaned -> {$phase} was accepted.");
            } catch (RuntimeException $e) {
                self::assertSame('Migration phase transition refused.', $e->getMessage());
            }
        }
        $version = $state->update($version, ['phase' => 'core_complete_with_preserved_data'])['version'];
        try {
            $state->update($version, ['phase' => 'core_complete_with_preserved_data']);
            self::fail('A final phase accepted another update.');
        } catch (RuntimeException $e) {
            self::assertSame('Migration phase transition refused.', $e->getMessage());
        }
        // Nothing was written by any refused update: the state is still the last accepted one.
        self::assertSame(['core_complete_with_preserved_data', $version], [$state->read()['phase'], $state->read()['version']]);
    }

    public function test_an_unknown_stored_phase_fails_closed(): void
    {
        mkdir($this->dir, 0700, true);
        file_put_contents($this->dir.'/state.json', json_encode(['phase' => 'mystery', 'version' => 2]));
        $state = new MigrationState($this->dir);

        foreach (['mystery', 'assessed', 'frozen'] as $phase) {
            try {
                $state->update(2, ['phase' => $phase]);
                self::fail('An unknown stored phase accepted a transition.');
            } catch (RuntimeException $e) {
                self::assertSame('Migration phase transition refused.', $e->getMessage());
            }
        }
        // The stored state is untouched by the refused updates.
        self::assertSame(['mystery', 2], [$state->read()['phase'], $state->read()['version']]);
    }

    public function test_null_removes_a_key(): void
    {
        $state = new MigrationState($this->dir);
        $version = $state->update(0, ['phase' => 'backed_up'])['version'];
        $version = $state->update($version, ['rename_map' => ['a' => 'legacy_a']])['version'];
        $next = $state->update($version, ['rename_map' => null]);

        self::assertArrayNotHasKey('rename_map', $next);
    }

    public function test_failed_is_terminal_and_reachable_only_after_the_wizard_began_to_change_things(): void
    {
        $state = new MigrationState($this->dir);
        $version = $state->update(0, ['phase' => 'backed_up'])['version'];
        try {
            $state->update($version, ['phase' => 'failed']);
            self::fail('A failure before anything was changed ended the run.');
        } catch (RuntimeException $e) {
            self::assertSame('Migration phase transition refused.', $e->getMessage());
        }

        $version = $state->update($version, ['phase' => 'frozen'])['version'];
        $version = $state->update($version, ['phase' => 'failed', 'failure' => ['reason' => 'x']])['version'];
        foreach (['failed', 'frozen', 'backed_up', 'importing', 'complete'] as $phase) {
            try {
                $state->update($version, ['phase' => $phase]);
                self::fail("failed -> {$phase} was accepted.");
            } catch (RuntimeException $e) {
                self::assertSame('Migration phase transition refused.', $e->getMessage());
            }
        }
        // Not even a change that keeps the phase.
        try {
            $state->update($version, ['operator_choices' => []]);
            self::fail('The failed state accepted a change.');
        } catch (RuntimeException $e) {
            self::assertSame('Migration phase transition refused.', $e->getMessage());
        }
        // The failed state is exactly what was last written.
        $stored = $state->read();
        self::assertSame('failed', $stored['phase']);
        self::assertSame(['reason' => 'x'], $stored['failure']);
        self::assertSame($version, $stored['version']);
    }
}
