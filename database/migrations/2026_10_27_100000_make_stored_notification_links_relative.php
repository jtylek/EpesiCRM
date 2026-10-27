<?php

use App\Support\RelativeUrl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bell notifications stored the full URL of their "Open" button, so a
 * database copied to another machine sent people back to the original site.
 * New ones are stored without the host (RelativeUrl); this rewrites the old.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        DB::table('notifications')
            ->where('data', 'like', '%http%')
            ->orderBy('id')
            ->each(function (object $row): void {
                $data = json_decode((string) $row->data, true);

                if (! is_array($data) || ! is_array($data['actions'] ?? null)) {
                    return;
                }

                $changed = false;

                foreach ($data['actions'] as &$action) {
                    $url = $action['url'] ?? null;

                    if (is_string($url) && preg_match('#^https?://#i', $url)) {
                        $path = RelativeUrl::of($url);

                        // A copy of another site's database: its links were made
                        // for a different directory, this installation's own
                        // subdirectory (if any) goes in front.
                        $base = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

                        $action['url'] = $base !== '' && ! str_starts_with($path, $base.'/') ? $base.$path : $path;
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
        // The original hosts are not recoverable.
    }
};
