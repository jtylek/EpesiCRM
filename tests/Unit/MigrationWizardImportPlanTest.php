<?php

use Epesi\Modules\MigrationWizard\Import\ImportPlan;
use PHPUnit\Framework\TestCase;

final class MigrationWizardImportPlanTest extends TestCase
{
    public function test_orders_dependencies_before_dependents(): void
    {
        $result = (new ImportPlan)->build([
            ['id' => 'contacts', 'depends_on' => ['users'], 'source_tables' => ['contact_data_1']],
            ['id' => 'users', 'source_tables' => ['user_login']],
        ], ['contact_data_1', 'user_login', 'unknown_custom']);
        self::assertSame(['users', 'contacts'], array_column($result['importers'], 'id'));
        self::assertFalse($result['execution_allowed']);
    }

    public function test_missing_source_table_fails_closed(): void
    {
        $this->expectException(\RuntimeException::class);
        (new ImportPlan)->build([['id' => 'users', 'source_tables' => ['missing']]], []);
    }

    public function test_dependency_cycle_fails_closed(): void
    {
        $this->expectException(\RuntimeException::class);
        (new ImportPlan)->build([
            ['id' => 'one', 'depends_on' => ['two']],
            ['id' => 'two', 'depends_on' => ['one']],
        ], []);
    }

    public function test_duplicate_identifier_fails_closed(): void
    {
        $this->expectException(\RuntimeException::class);
        (new ImportPlan)->build([['id' => 'users'], ['id' => 'users']], []);
    }
}
