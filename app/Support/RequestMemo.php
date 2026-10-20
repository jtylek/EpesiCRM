<?php

namespace App\Support;

use Closure;

/**
 * Answers kept for the rest of one request: bound `scoped`, so a queue job, a
 * Livewire request or a test application starts empty. Whatever changes what
 * it holds must call forget() with the same key prefix.
 */
class RequestMemo
{
    /** @var array<string, mixed> */
    protected array $values = [];

    public function remember(string $key, Closure $build): mixed
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $this->values[$key] = $build();
    }

    /** Drop every answer whose key starts with $prefix. */
    public function forget(string $prefix): void
    {
        foreach (array_keys($this->values) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->values[$key]);
            }
        }
    }
}
