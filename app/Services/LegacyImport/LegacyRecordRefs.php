<?php

namespace App\Services\LegacyImport;

use App\Console\Commands\ImportLegacyData;
use Epesi\Modules\RecordBrowser\Models\RecordLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;

/**
 * A legacy "tab/id" reference — a `__RECORDSETS__` field's value, "task/7" —
 * as the record it became here, by the tab each importer reads and the model
 * it writes (Importer::legacyTab()/modelClass()): the importers already know
 * both ends, so no second table of tab names to keep up to date.
 *
 * A tab no importer reads (a recordset not ported yet) or a record not
 * imported yet resolves to nothing, for the caller to report. Id maps are
 * built on first use, so a record imported later in the same run resolves on
 * the next run.
 */
class LegacyRecordRefs
{
    /** @var array<class-string<Model>, LegacyIdMap> */
    private array $maps = [];

    /**
     * @param  array<string, class-string<Model>>  $models  legacy tab => model
     */
    public function __construct(private readonly array $models) {}

    /** From every importer `import:legacy` runs, core and module-registered. */
    public static function fromImporters(): self
    {
        $registry = app(ImporterRegistry::class);
        $models = [];

        foreach ([...ImportLegacyData::IMPORTERS, ...$registry->before(), ...$registry->after()] as $importer) {
            if (! is_subclass_of($importer, Importer::class)) {
                continue;
            }

            // Only for its two names: an importer's constructor builds id maps.
            $instance = (new ReflectionClass($importer))->newInstanceWithoutConstructor();
            $models[$instance->legacyTab()] = $instance->modelClass();
        }

        return new self($models);
    }

    /** "company:12" (RecordLink's token) for legacy "company/49", or null. */
    public function token(string $tab, int $legacyId): ?string
    {
        $class = $this->models[$tab] ?? null;

        if ($class === null) {
            return null;
        }

        $id = ($this->maps[$class] ??= LegacyIdMap::for($class))->get($legacyId);

        return $id === null ? null : RecordLink::tokenFor(Relation::getMorphAlias($class), $id);
    }
}
