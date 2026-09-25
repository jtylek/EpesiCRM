<?php

namespace App\Services\LegacyImport;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * legacy_id -> new-id lookup for one already-imported model, built once per
 * Importer::run() and reused for every FK/pivot resolution and history-value
 * translation that importer needs. Backed by the `legacy_id` column added by
 * the add_legacy_id_to_migrated_tables migration, not a separate join table
 * (history replay does one such lookup per changed field per edit).
 */
class LegacyIdMap
{
    /** @var array<int, int> */
    private array $map;

    private function __construct(array $map)
    {
        $this->map = $map;
    }

    public static function for(string $modelClass): self
    {
        $query = $modelClass::query();

        // Not every imported model is soft-deletable (User isn't).
        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        return new self(
            $query->whereNotNull('legacy_id')->pluck('id', 'legacy_id')->all()
        );
    }

    public function get(?int $legacyId): ?int
    {
        if ($legacyId === null) {
            return null;
        }

        return $this->map[$legacyId] ?? null;
    }

    public function remember(int $legacyId, int $id): void
    {
        $this->map[$legacyId] = $id;
    }

    /**
     * @param  list<int>  $legacyIds
     * @return list<int>
     */
    public function getMany(array $legacyIds): array
    {
        return array_values(array_filter(array_map($this->get(...), $legacyIds)));
    }
}
