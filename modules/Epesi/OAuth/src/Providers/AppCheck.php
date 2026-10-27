<?php

namespace Epesi\Modules\OAuth\Providers;

/**
 * One step of an app test (OAuthProvider::testApp()): what was checked,
 * whether it passed, and what was found. The label and message are shown as
 * they stand, already translated, so they never carry a secret or a token.
 */
final class AppCheck
{
    public function __construct(
        public readonly string $label,
        public readonly bool $passed,
        public readonly string $message,
    ) {}

    public static function pass(string $label, string $message): self
    {
        return new self($label, true, $message);
    }

    public static function fail(string $label, string $message): self
    {
        return new self($label, false, $message);
    }
}
