<?php

namespace App\Support\Setup;

/**
 * The module pages shown after installation, in the order given — lower
 * first; equal orders keep registration order.
 */
class SetupSteps
{
    /** @var array<string, array{class: class-string<SetupStep>, order: int}> */
    protected static array $steps = [];

    /**
     * @param  string  $key  also the step's form state path, so no dots — a
     *                       dot would nest it
     * @param  class-string<SetupStep>  $class
     */
    public static function register(string $key, string $class, int $order = 100): void
    {
        if (str_contains($key, '.')) {
            throw new \InvalidArgumentException("Setup step key \"{$key}\" can't contain a dot.");
        }

        static::$steps[$key] = ['class' => $class, 'order' => $order];
    }

    /**
     * @return array<string, SetupStep> key => step
     */
    public static function all(): array
    {
        return collect(static::$steps)
            ->sortBy('order')
            ->map(fn (array $step): SetupStep => app($step['class']))
            ->all();
    }

    /** For tests. */
    public static function flush(): void
    {
        static::$steps = [];
    }
}
