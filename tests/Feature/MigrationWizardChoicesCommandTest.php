<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Only the refusals: they stop before the choices are stored, so
 * nothing is written to the migration state. Storing is covered by the runner tests.
 */
final class MigrationWizardChoicesCommandTest extends TestCase
{
    public function test_a_pattern_is_not_a_name(): void
    {
        $this->artisan('epesi:migrate-legacy', ['--preserve' => 'custom_*'])
            ->expectsOutputToContain('Choices not stored')
            ->assertFailed();
    }

    public function test_the_unknown_token_cannot_be_discarded(): void
    {
        $this->artisan('epesi:migrate-legacy', ['--discard' => '@unknown', '--yes' => true])
            ->expectsOutputToContain('Choices not stored')
            ->assertFailed();
    }

    public function test_a_name_cannot_be_both_preserved_and_discarded(): void
    {
        $this->artisan('epesi:migrate-legacy', ['--preserve' => 'old_cache', '--discard' => 'old_cache', '--yes' => true])
            ->expectsOutputToContain('Cannot both preserve and discard')
            ->assertFailed();
    }

    public function test_discarding_is_never_silent(): void
    {
        $this->artisan('epesi:migrate-legacy', ['--discard' => 'old_cache', '--no-interaction' => true])
            ->expectsOutputToContain('Discarding was not confirmed')
            ->assertFailed();
    }
}
