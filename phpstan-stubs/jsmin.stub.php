<?php
// Minimal JSMin stub.
// Provide class and a case-insensitive alias to silence case mismatch reports.

class JSMin {
    private string $input;

    public function __construct(string $input = '')
    {
        $this->input = $input;
    }

    public function minify(): string
    {
        return $this->input;
    }

    public static function minifyStatic(string $input): string
    {
        return $input;
    }
}

// Some legacy code may reference JSmin (different case). Create an alias so PHPStan
// doesn't complain about incorrect case usage.
if (!class_exists('JSmin', false)) {
    class_alias('JSMin', 'JSmin');
}
