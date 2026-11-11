<?php

namespace Epesi\Modules\MigrationWizard\Import;

use RuntimeException;

/** Validate an explicit mapping against both schema inventories before importing. */
final class FieldMapping
{
    public function validate(array $mapping, array $sourceColumns, array $targetColumns): array
    {
        if ($mapping === [] || $sourceColumns === [] || $targetColumns === []) {
            throw new RuntimeException('Certified field mapping and schema inventories are required.');
        }
        $source = array_fill_keys($sourceColumns, true);
        $target = array_fill_keys($targetColumns, true);
        $seenTargets = [];
        $normalized = [];
        foreach ($mapping as $from => $to) {
            if (! is_string($from) || ! is_string($to)
                || ! isset($source[$from]) || ! isset($target[$to])
                || isset($seenTargets[$to])) {
                throw new RuntimeException('Field mapping references unknown or duplicate columns.');
            }
            $seenTargets[$to] = true;
            $normalized[$from] = $to;
        }
        return ['mapping' => $normalized, 'unmapped_source_columns' => array_values(array_diff($sourceColumns, array_keys($normalized))),
            'mode' => 'validated_mapping_only', 'execution_allowed' => false];
    }
}
