<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Watchdog notifications used to link to the record's History modal
 * (`?action=history`); they now open the record itself. This rewrites the
 * links already stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        DB::table('notifications')
            ->where('data', 'like', '%action=history%')
            ->orderBy('id')
            ->each(function (object $row): void {
                $data = json_decode((string) $row->data, true);

                if (! is_array($data) || ! is_array($data['actions'] ?? null)) {
                    return;
                }

                $changed = false;

                foreach ($data['actions'] as &$action) {
                    $url = $action['url'] ?? null;

                    if (is_string($url) && str_ends_with($url, '?action=history')) {
                        $action['url'] = substr($url, 0, -strlen('?action=history'));
                        $changed = true;
                    }
                }
                unset($action);

                if ($changed) {
                    DB::table('notifications')->where('id', $row->id)->update([
                        'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // The links are not restored to History.
    }
};
