<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One installed module — the Laravel-Epesi equivalent of a row in old Epesi's
 * `modules` table (ModuleManager's registry), and the source of truth behind
 * App\Support\Modules\ModuleRegistry. Rows are written only by
 * App\Services\Modules\ModuleInstaller, never by a Filament form.
 */
class Module extends Model
{
    use LogsActivity;

    protected $fillable = [
        'module_id',
        'name',
        'description',
        'version',
        'path',
        'namespace',
        'provider_class',
        'plugin_class',
        'panels',
        'manifest',
        'enabled',
        'installed_at',
    ];

    protected function casts(): array
    {
        return [
            'panels' => 'array',
            'manifest' => 'array',
            'enabled' => 'boolean',
            'installed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logOnly(['module_id', 'version', 'enabled'])
            ->useLogName('module');
    }

    /**
     * A module the application itself is built on (the RecordBrowser engine, and
     * anything else that ships core behaviour). Declared as `"core": true` in
     * module.json; disabling or uninstalling one is refused, because core code
     * `extends`/`use`s its classes and unregistering its PSR-4 prefix fatals the
     * next request rather than degrading.
     */
    public function isCore(): bool
    {
        return (bool) ($this->manifest['core'] ?? false);
    }

    /**
     * @return array<int, string> module ids this module declares a dependency on
     */
    public function requires(): array
    {
        return array_values(array_filter((array) ($this->manifest['requires'] ?? []), 'is_string'));
    }

    /**
     * Modules that name this one in their `requires:`. The reverse of
     * ModuleInstaller::assertRequirements(), which only ever runs on install —
     * without this, disabling a dependency is a silent way to break everything
     * built on it.
     *
     * Filtered in PHP rather than queried: `manifest` is a JSON column whose
     * operators differ across MySQL/MariaDB/SQLite, and the table has as many
     * rows as the install has modules.
     *
     * @return Collection<int, static>
     */
    public function dependents(bool $enabledOnly = false): Collection
    {
        return static::query()
            ->where('module_id', '!=', $this->module_id)
            ->when($enabledOnly, fn ($query) => $query->where('enabled', true))
            ->get()
            ->filter(fn (self $module): bool => in_array($this->module_id, $module->requires(), true))
            ->values();
    }

    /**
     * Registered modules whose files live inside this module's directory —
     * "Epesi/CRM/Contacts/Activities" inside "Epesi/CRM/Contacts". Old Epesi's
     * own tree has 24 of these out of 130, so the layout is expected rather
     * than exotic; the installer has to know about them because a directory
     * delete or a zip replace would otherwise take them with it.
     *
     * @return Collection<int, static>
     */
    public function nestedModules(): Collection
    {
        return static::query()
            ->where('module_id', '!=', $this->module_id)
            ->where('path', 'like', $this->path.'/%')
            ->orderBy('path')
            ->get();
    }

    /**
     * Absolute path of the module's own directory, e.g. <app>/modules/Epesi/Notes.
     */
    public function directory(): string
    {
        return static::directoryFor($this->path);
    }

    public static function directoryFor(string $path): string
    {
        return rtrim((string) config('modules.path'), '/\\')
            .DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    public function directoryExists(): bool
    {
        return is_dir($this->directory());
    }
}
