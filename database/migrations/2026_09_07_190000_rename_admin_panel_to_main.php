<?php

use App\Support\Modules\ModuleRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renames the CRM panel's id from "admin" to "main", so it stops reading as a
 * near-synonym of the separate `administration` panel next to it.
 *
 * The id is not only in MainPanelProvider: every module declares the panels it
 * registers into, and ModuleRegistry::pluginsFor() matches those stored strings
 * against the panel being built. The module.json files ship the new value, but
 * an already-installed module has its copy in the `modules` row — so without
 * this pass every CRM resource silently disappears from the panel (no error:
 * the plugin list simply comes back empty).
 *
 * `manifest` holds the manifest as installed and is rewritten alongside, so a
 * later re-register or zip update doesn't diff against a stale panel list.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rewrite('admin', 'main');
    }

    public function down(): void
    {
        $this->rewrite('main', 'admin');
    }

    protected function rewrite(string $from, string $to): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        foreach (DB::table('modules')->get(['id', 'panels', 'manifest']) as $row) {
            $panels = json_decode((string) $row->panels, true);
            $manifest = json_decode((string) $row->manifest, true);

            if (! is_array($panels) || ! in_array($from, $panels, true)) {
                continue;
            }

            $panels = array_map(fn (string $panel): string => $panel === $from ? $to : $panel, $panels);

            if (is_array($manifest) && is_array($manifest['panels'] ?? null)) {
                $manifest['panels'] = $panels;
            }

            DB::table('modules')->where('id', $row->id)->update([
                'panels' => json_encode(array_values($panels)),
                'manifest' => json_encode($manifest),
            ]);
        }

        // Same reason ModuleInstaller does it after every write: the panel list
        // requests actually read is the generated bootstrap/cache file, not the
        // table.
        ModuleRegistry::refresh();
    }
};
