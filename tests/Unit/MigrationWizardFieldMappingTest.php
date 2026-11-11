<?php

use Epesi\Modules\MigrationWizard\Import\FieldMapping;
use PHPUnit\Framework\TestCase;

final class MigrationWizardFieldMappingTest extends TestCase
{
    public function test_reports_unmapped_legacy_columns_without_dropping_them(): void
    {
        $result = (new FieldMapping)->validate(['id' => 'legacy_id', 'name' => 'display_name'],
            ['id', 'name', 'custom_field'], ['legacy_id', 'display_name']);
        self::assertSame(['custom_field'], $result['unmapped_source_columns']);
        self::assertFalse($result['execution_allowed']);
    }

    public function test_rejects_unknown_target_column(): void
    {
        $this->expectException(\RuntimeException::class);
        (new FieldMapping)->validate(['id' => 'missing'], ['id'], ['legacy_id']);
    }

    public function test_rejects_duplicate_target_column(): void
    {
        $this->expectException(\RuntimeException::class);
        (new FieldMapping)->validate(['id' => 'legacy_id', 'other' => 'legacy_id'],
            ['id', 'other'], ['legacy_id']);
    }
}
