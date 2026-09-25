<?php

namespace App\Support\Setup;

/**
 * Sets keys in the application's .env file, keeping everything else in it —
 * comments, order, keys it doesn't touch — as it was.
 */
class EnvFile
{
    public function __construct(protected ?string $path = null)
    {
        $this->path ??= app()->environmentFilePath();
    }

    public function writable(): bool
    {
        return is_file($this->path) ? is_writable($this->path) : is_writable(dirname($this->path));
    }

    /**
     * @param  array<string, string|int|bool|null>  $values
     */
    public function set(array $values): void
    {
        $contents = is_file($this->path) ? (string) file_get_contents($this->path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->format($value);
            $pattern = '/^#?\s*'.preg_quote($key, '/').'=.*$/m';

            // Replace an existing (or commented-out) line in place, so the
            // key stays in the section .env.example put it in.
            $contents = preg_match($pattern, $contents)
                ? (string) preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $contents, 1)
                : rtrim($contents)."\n".$line."\n";
        }

        if (file_put_contents($this->path, $contents) === false) {
            throw new \RuntimeException("Could not write {$this->path}.");
        }
    }

    protected function format(string|int|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        // Quoted whenever dotenv would otherwise misread it: spaces, "#"
        // (a comment), "$" (variable expansion) or quotes.
        if ($value === '' || preg_match('/^[A-Za-z0-9_.\/:@+\-]+$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
