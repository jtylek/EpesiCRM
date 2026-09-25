<?php

namespace Epesi\Modules\CommonData\LegacyImport;

use App\Services\LegacyImport\ImportSummary;
use Epesi\Modules\CommonData\Models\CommonDataNode;
use Illuminate\Support\Facades\DB;

/**
 * Shared reference data out of the legacy `utils_commondata_tree`.
 *
 * Registered as the `commondata` tab by CommonDataServiceProvider, so the tab
 * exists exactly when this module does — see ImporterRegistry.
 *
 * Not an Importer subclass: that base reads RecordBrowser-shaped tables
 * (`<tab>_field`/`<tab>_data_1`/`<tab>_edit_history*`), and commondata is a
 * plain tree with no field definitions, no ownership and no edit history of its
 * own.
 *
 * **Matches on `path`, not `legacy_id`** — the deliberate exception to this
 * command's usual rule. A module seeds the arrays it owns (`CRM/Priority`) at
 * install time, so those rows exist before any import runs and have no
 * legacy_id; the usual guard would read them as foreign fixtures and refuse.
 * The path is the natural key on both sides, so a seeded `CRM/Priority` merges
 * with the legacy one instead of colliding with it.
 */
class CommonDataImporter
{
    /**
     * Legacy keys this port has deliberately renamed, by the legacy path they
     * sit at. Epesi's Contacts_Groups ships the key "custm"
     * (CRM/Contacts/ContactsInstall.php); this port spells it out, and
     * ContactsImporter::GROUP_KEY_REMAP applies the same rename to the values
     * stored on each contact — the list and the values it validates have to
     * agree, so the two maps are kept deliberately in step.
     *
     * @var array<string, string> legacy path => replacement key
     */
    protected const KEY_REMAP = [
        'Contacts_Groups/custm' => 'customer',
    ];

    /**
     * $withHistory is accepted and ignored: this table has no
     * `*_edit_history` counterpart in Epesi, so there is nothing for
     * --no-history to skip. The parameter keeps the signature every other
     * importer is called with.
     */
    public function run(bool $withHistory = true): ImportSummary
    {
        $summary = new ImportSummary;

        $rows = DB::connection('legacy')
            ->table('utils_commondata_tree')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        /** @var array<int, list<object>> legacy parent id => its children */
        $childrenOf = [];

        foreach ($rows as $row) {
            $childrenOf[(int) $row->parent_id][] = $row;
        }

        // Epesi's root marker. Anything else pointing at a row that isn't in
        // the table would silently vanish from the walk below, so say so.
        $reachable = 0;

        CommonDataNode::withoutReadonlyProtection(function () use ($childrenOf, $summary, &$reachable): void {
            $reachable = $this->importChildren($childrenOf, parentLegacyId: -1, parentId: null, parentPath: '', summary: $summary);
        });

        if ($reachable !== $rows->count()) {
            $summary->warn(($rows->count() - $reachable).' rows were skipped: their parent is missing from utils_commondata_tree.');
        }

        return $summary;
    }

    /**
     * @param  array<int, list<object>>  $childrenOf
     * @param  string  $parentPath  the *legacy* path of $parentLegacyId, which
     *                              KEY_REMAP is keyed by — a renamed key must
     *                              not change what its children look up under
     * @return int rows imported, this level and everything below it
     */
    protected function importChildren(array $childrenOf, int $parentLegacyId, ?int $parentId, string $parentPath, ImportSummary $summary): int
    {
        $imported = 0;

        foreach ($childrenOf[$parentLegacyId] ?? [] as $row) {
            $legacyKey = $this->decode($row->akey);
            $legacyPath = $parentPath === '' ? $legacyKey : $parentPath.'/'.$legacyKey;
            $key = self::KEY_REMAP[$legacyPath] ?? $legacyKey;

            // Epesi rejects these on the way in, so a row carrying one is
            // corrupt rather than merely awkward; importing it would break
            // every path-prefix query below it.
            if (str_contains($key, '/')) {
                $summary->warn("Skipped commondata key \"{$key}\" (id {$row->id}): keys cannot contain \"/\".");

                continue;
            }

            $node = $this->upsert($row, $key, $parentId, $summary);

            $imported++;
            $imported += $this->importChildren($childrenOf, (int) $row->id, $node->getKey(), $legacyPath, $summary);
        }

        return $imported;
    }

    protected function upsert(object $row, string $key, ?int $parentId, ImportSummary $summary): CommonDataNode
    {
        $attributes = [
            'parent_id' => $parentId,
            'key' => $key,
            'value' => $row->value === null ? null : $this->decode($row->value),
            'readonly' => (bool) $row->readonly,
            'position' => (int) $row->position,
        ];

        $path = $parentId
            ? CommonDataNode::query()->whereKey($parentId)->value('path').'/'.$key
            : $key;

        $node = CommonDataNode::query()->where('path', $path)->first();

        if ($node) {
            $node->fill($attributes)->save();
            $summary->updated++;

            return $node;
        }

        $summary->created++;

        return CommonDataNode::create($attributes);
    }

    /**
     * Epesi runs keys and values through htmlspecialchars() on the way in, so
     * an ampersand or a quote is stored encoded ("&quot;"). Blade escapes on
     * output here, so storing the encoded form would double-encode it and show
     * the entity to the user.
     */
    protected function decode(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
    }
}
