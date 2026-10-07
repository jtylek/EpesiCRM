<?php

namespace App\Services\LegacyImport;

/**
 * How a module contributes a tab to `php artisan import:legacy`.
 *
 * The command's own importers are a fixed list, because the tables they read
 * and the order they need are both fixed. A module's are not: it may not be
 * installed, and the core app must not name its classes — a hardcoded
 * `Epesi\Modules\...\SomeImporter` in app/ would break the command outright the
 * day that module is removed. Registering from the module's service provider
 * inverts that: no module, no registration, no tab, and nothing to load.
 *
 * Register from a provider's boot(), not register() — an importer is only ever
 * resolved while a console command runs, and boot() is where a module's other
 * wiring already happens.
 */
class ImporterRegistry
{
    /** @var array<string, array{importer: class-string, first: bool, last: bool}> */
    protected array $importers = [];

    /**
     * @param  string  $tab  what the user types after `import:legacy`
     * @param  class-string  $importer  a class with run(bool $withHistory): ImportSummary
     * @param  bool  $first  run before the core tabs rather than after them —
     *                       for reference data the rest of the import reads
     * @param  bool  $last  run after every other module's tab — for importers that link
     *                      to records other importers bring over (mail, notes), so
     *                      the order does not depend on which provider boots first
     */
    public function register(string $tab, string $importer, bool $first = false, bool $last = false): void
    {
        $this->importers[$tab] = ['importer' => $importer, 'first' => $first, 'last' => $last];
    }

    /** @return array<string, class-string> tab => importer, registration order */
    public function before(): array
    {
        return $this->filter(first: true, last: false);
    }

    /** @return array<string, class-string> tab => importer, registration order */
    public function after(): array
    {
        return $this->filter(first: false, last: false);
    }

    /** @return array<string, class-string> tab => importer, registration order */
    public function last(): array
    {
        return $this->filter(first: false, last: true);
    }

    /** @return array<string, class-string> */
    protected function filter(bool $first, bool $last): array
    {
        return array_map(
            fn (array $entry): string => $entry['importer'],
            array_filter($this->importers, fn (array $entry): bool => $entry['first'] === $first && $entry['last'] === $last)
        );
    }
}
