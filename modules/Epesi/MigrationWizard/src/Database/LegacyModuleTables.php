<?php

namespace Epesi\Modules\MigrationWizard\Database;

use Epesi\Modules\MigrationWizard\Verification\MigrationVerifier;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The tables the Premium and Custom modules that stay create, read from their *Install.php files. The files are only
 * tokenized, never included or run. Recognised: install_new_recordset('name') with its side tables, CreateTable('name')
 * and CREATE TABLE in a string. A name built at runtime cannot be read; the prefixes in UnownedLegacyData are the safety
 * net for those.
 */
final class LegacyModuleTables
{
    /** @return array<string, true> original table name => true */
    public function declared(string $root): array
    {
        $tables = [];
        foreach (['modules/Premium', 'modules/Custom', 'legacy-modules/Premium', 'legacy-modules/Custom'] as $folder) {
            $path = rtrim($root, '/\\').'/'.$folder;
            if (! is_dir($path)) {
                continue;
            }
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), 'Install.php')) {
                    $tables += $this->fromSource((string) @file_get_contents($file->getPathname()));
                }
            }
        }

        return $tables;
    }

    /** @return array<string, true> */
    public function fromSource(string $source): array
    {
        $tables = [];
        $tokens = array_values(array_filter(
            @token_get_all($source),
            static fn ($token): bool => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));
        foreach ($tokens as $i => $token) {
            if (! is_array($token)) {
                continue;
            }
            if ($token[0] === T_CONSTANT_ENCAPSED_STRING && preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?(\w+)/i', $token[1], $found)) {
                foreach ($found[1] as $name) {
                    $tables[$name] = true;
                }
            }
            if ($token[0] !== T_STRING || ($tokens[$i + 1] ?? null) !== '(') {
                continue;
            }
            $argument = $tokens[$i + 2] ?? null;
            if (! is_array($argument) || $argument[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            $name = trim($argument[1], "'\"");
            if (preg_match('/^\w+$/', $name) !== 1) {
                continue;
            }
            $function = strtolower($token[1]);
            if ($function === 'createtable' || $function === 'create_table') {
                $tables[$name] = true;
            } elseif ($function === 'install_new_recordset') {
                $tables[$name.'_data_1'] = true;
                foreach (MigrationVerifier::SIDE_TABLES as $suffix) {
                    $tables[$name.'_'.$suffix] = true;
                }
            }
        }

        return $tables;
    }
}
