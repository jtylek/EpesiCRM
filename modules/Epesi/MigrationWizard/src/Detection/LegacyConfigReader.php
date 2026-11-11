<?php

namespace Epesi\Modules\MigrationWizard\Detection;

use RuntimeException;

/**
 * Reads only literal define('DATABASE_*', 'value') statements.
 * Never includes or evaluates the untrusted legacy configuration.
 */
final class LegacyConfigReader
{
    private const KEYS = [
        'DATABASE_HOST', 'DATABASE_PORT', 'DATABASE_USER',
        'DATABASE_PASSWORD', 'DATABASE_NAME', 'DATABASE_DRIVER',
    ];

    public function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Legacy configuration is not readable.');
        }

        $source = file_get_contents($path);
        if ($source === false) {
            throw new RuntimeException('Cannot read legacy configuration.');
        }

        $tokens = token_get_all($source);
        $values = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING || strcasecmp($tokens[$i][1], 'define') !== 0) {
                continue;
            }

            $j = $i + 1;
            $next = static function () use (&$j, $tokens, $count) {
                while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $j++;
                }
                return $tokens[$j++] ?? null;
            };
            $open = $next();
            $key = $next();
            $comma = $next();
            $value = $next();
            $close = $next();
            if ($open !== '(' || ! is_array($key) || $key[0] !== T_CONSTANT_ENCAPSED_STRING ||
                $comma !== ',' || ! is_array($value) || $value[0] !== T_CONSTANT_ENCAPSED_STRING ||
                $close !== ')') {
                continue;
            }

            $name = $this->decodeLiteral($key[1]);
            if (! in_array($name, self::KEYS, true)) {
                continue;
            }
            if (array_key_exists($name, $values)) {
                throw new RuntimeException('Duplicate legacy configuration key: '.$name);
            }
            $values[$name] = $this->decodeLiteral($value[1]);
        }

        foreach (['DATABASE_HOST', 'DATABASE_USER', 'DATABASE_NAME'] as $required) {
            if (! isset($values[$required]) || $values[$required] === '') {
                throw new RuntimeException('Missing literal legacy configuration key: '.$required);
            }
        }

        $port = $values['DATABASE_PORT'] ?? '3306';
        if (! ctype_digit((string) $port) || (int) $port < 1 || (int) $port > 65535) {
            throw new RuntimeException('Invalid legacy database port.');
        }
        $values['DATABASE_PORT'] = (int) $port;

        return $values;
    }

    private function decodeLiteral(string $token): string
    {
        $quote = $token[0];
        $body = substr($token, 1, -1);
        if ($quote === "'") {
            return str_replace(["\\\\", "\\'"], ["\\", "'"], $body);
        }

        // Reject interpolated or escape-heavy values rather than guess credentials.
        if (str_contains($body, '\\')) {
            throw new RuntimeException('Unsupported escaped legacy configuration value.');
        }
        return $body;
    }
}
