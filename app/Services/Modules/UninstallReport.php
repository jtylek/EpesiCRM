<?php

namespace App\Services\Modules;

/** What an uninstall did, for the screen or the console to tell. */
final class UninstallReport
{
    /**
     * @param  array<int, string>  $warnings  what the data removal could not do
     */
    public function __construct(
        public readonly bool $dataDeleted,
        public readonly bool $filesKept,
        public readonly array $warnings = [],
    ) {}

    /** One paragraph for a notification or the console. */
    public function summary(): string
    {
        $lines = [$this->dataDeleted ? __('Its data was deleted.') : __('Its data was kept; reinstalling reuses it.')];

        if ($this->filesKept) {
            $lines[] = __('Its files were kept because the directory is a git repository.');
        }

        return implode(' ', [...$lines, ...$this->warnings]);
    }
}
