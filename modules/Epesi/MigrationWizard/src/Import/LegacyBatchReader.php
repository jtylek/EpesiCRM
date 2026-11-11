<?php

namespace Epesi\Modules\MigrationWizard\Import;

use PDO;
use RuntimeException;

/**
 * Read-only keyset pagination of a certified single-integer-key source table.
 * Table/column identifiers are never taken directly from an operator request.
 */
final class LegacyBatchReader
{
    public function read(PDO $source, string $table, string $idColumn, int $afterId, int $limit, array $certifiedColumns): array
    {
        if ($afterId < 0 || $limit < 1 || $limit > 1000 || $certifiedColumns === []
            || ! in_array($idColumn, $certifiedColumns, true)) {
            throw new RuntimeException('Invalid source batch parameters.');
        }
        foreach (array_merge([$table], $certifiedColumns) as $identifier) {
            if (! is_string($identifier) || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $identifier)) {
                throw new RuntimeException('Unsafe source identifier.');
            }
        }
        $columns = implode(', ', array_map(static fn (string $column): string => '`'.$column.'`', $certifiedColumns));
        $sql = 'SELECT '.$columns.' FROM `'.$table.'` WHERE `'.$idColumn.'` > :after ORDER BY `'.$idColumn.'` ASC LIMIT '.(int) $limit;
        $statement = $source->prepare($sql);
        $statement->bindValue(':after', $afterId, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $previous = $afterId;
        foreach ($rows as $row) {
            $id = $row[$idColumn] ?? null;
            if ((! is_int($id) && (! is_string($id) || ! ctype_digit($id)))
                || (int) $id <= $previous || (string) (int) $id !== (string) $id) {
                throw new RuntimeException('Source IDs must be strictly increasing positive integers.');
            }
            $previous = (int) $id;
        }
        return ['rows' => $rows, 'last_source_id' => $previous, 'has_more' => count($rows) === $limit];
    }
}
