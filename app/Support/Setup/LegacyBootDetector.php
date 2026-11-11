<?php

namespace App\Support\Setup;

/**
 * Pre-Laravel, side-effect-free legacy installation detection.
 * A readable legacy configuration is a hint, not authorization to migrate.
 */
final class LegacyBootDetector
{
    public static function detect(string $basePath): string
    {
        $config = $basePath.'/data/config.php';
        if (! is_file($config) || ! is_readable($config)) {
            return 'fresh';
        }
        $source = file_get_contents($config);
        if ($source === false) {
            return 'fresh';
        }
        // Never include/evaluate legacy PHP at bootstrap.
        $tokens = token_get_all($source);
        $found = [];
        for ($i = 0, $n = count($tokens); $i < $n; $i++) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING || strcasecmp($tokens[$i][1], 'define') !== 0) {
                continue;
            }
            $j = $i + 1;
            $next = static function () use (&$j, $tokens, $n) {
                while ($j < $n && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $j++;
                }
                return $tokens[$j++] ?? null;
            };
            $open = $next();
            $name = $next();
            $comma = $next();
            $value = $next();
            $close = $next();
            if ($open !== '(' || $comma !== ',' || $close !== ')' || ! is_array($name) || ! is_array($value) ||
                $name[0] !== T_CONSTANT_ENCAPSED_STRING || $value[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            $key = trim($name[1], "'\"");
            if (in_array($key, ['DATABASE_HOST', 'DATABASE_USER', 'DATABASE_NAME'], true)) {
                $found[$key] = true;
            }
        }
        return count($found) === 3 ? 'legacy' : 'fresh';
    }
}
