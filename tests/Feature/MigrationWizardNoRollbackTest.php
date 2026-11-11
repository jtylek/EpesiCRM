<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Tests\TestCase;

class MigrationWizardNoRollbackTest extends TestCase
{
    public function test_the_migration_command_has_resume_but_no_rollback_option(): void
    {
        $definition = Artisan::all()['epesi:migrate-legacy']->getDefinition();

        self::assertTrue($definition->hasOption('resume'));
        self::assertTrue($definition->hasOption('confirm-backup'));
        foreach (['confirm-restore-tested', 'record-backup', 'create-backup'] as $removed) {
            self::assertFalse($definition->hasOption($removed), $removed);
        }
        self::assertFalse($definition->hasOption('rollback'));
    }

    public function test_the_option_is_refused_as_unknown(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('rollback');

        Artisan::call('epesi:migrate-legacy', ['--rollback' => true]);
    }
}
