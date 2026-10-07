<?php

namespace Epesi\Modules\Shoutbox\LegacyImport;

use App\Models\User;
use App\Services\LegacyImport\ImportSummary;
use App\Services\LegacyImport\LegacyIdMap;
use Epesi\Modules\Shoutbox\Models\Message;
use Illuminate\Support\Facades\DB;

/**
 * The legacy `apps_shoutbox_messages`, registered as the `shoutbox` tab of
 * `import:legacy`. Upserted on the legacy id, so a re-run updates instead of
 * duplicating. Messages made directly in this app (no legacy_id) are left
 * alone: a cutover that wants them gone clears the table first.
 *
 * Needs the `users` tab: a message whose sender has no imported user is
 * skipped, and so is a private one whose recipient has none (importing it
 * without a recipient would make it public).
 */
class ShoutboxImporter
{
    /** @var array<string, array<int, string|null>> */
    private array $names = [];

    public function run(bool $withHistory = true): ImportSummary
    {
        $summary = new ImportSummary;
        $legacy = DB::connection('legacy');

        if (! $legacy->getSchemaBuilder()->hasTable('apps_shoutbox_messages')) {
            $summary->warn('No apps_shoutbox_messages table in the legacy database.');

            return $summary;
        }

        $users = LegacyIdMap::for(User::class);
        $skipped = 0;

        foreach ($legacy->table('apps_shoutbox_messages')->orderBy('id')->get() as $row) {
            $author = $users->get((int) $row->base_user_login_id);
            $recipient = $row->to_user_login_id === null ? null : $users->get((int) $row->to_user_login_id);

            if ($author === null || ($row->to_user_login_id !== null && $recipient === null)) {
                $skipped++;

                continue;
            }

            $message = Message::query()->firstOrNew(['legacy_id' => (int) $row->id]);
            $message->exists ? $summary->updated++ : $summary->created++;

            $message->forceFill([
                'user_id' => $author,
                'to_user_id' => $recipient,
                'message' => $this->plainText((string) $row->message),
                'deleted' => (bool) $row->deleted,
                'created_at' => $row->posted_on,
                'updated_at' => $row->posted_on,
            ])->save();
        }

        if ($skipped > 0) {
            $summary->warn("Skipped {$skipped} shoutbox message(s) whose sender or recipient is not an imported user (run the users tab first).");
        }

        return $summary;
    }

    /**
     * Legacy kept the text HTML-escaped ("it&#039;s") and let users format it
     * with BBCode; this app escapes on output and shows plain text. A
     * [contact]id[/contact] becomes the contact's name, [url]x[/url] its
     * address, and the formatting tags just go.
     */
    private function plainText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $text = preg_replace_callback(
            '~\[(contact|company)\](\d+)\[/(?:contact|company)\]~i',
            fn (array $m): string => $this->recordName(strtolower($m[1]), (int) $m[2]) ?? $m[2],
            $text,
        );

        $text = preg_replace_callback(
            '~\[url=([^\]]+)\](.*?)\[/url\]~is',
            fn (array $m): string => trim($m[2]) === '' || trim($m[2]) === trim($m[1]) ? trim($m[1]) : trim($m[2]).' ('.trim($m[1]).')',
            $text,
        );

        // Whatever tags are left: only the ones Epesi's BBCode knew, so a
        // plain "[ok]" in a message stays.
        return trim((string) preg_replace('~\[/?(?:b|i|u|s|url|img|email|quote|code|color|size|font|center|left|right|list|\*)(?:=[^\]]*)?\]~i', '', $text));
    }

    /** The name a legacy [contact]/[company] BBCode showed, or null when the record is gone. */
    private function recordName(string $type, int $id): ?string
    {
        return $this->names[$type][$id] ??= $this->lookupName($type, $id);
    }

    private function lookupName(string $type, int $id): ?string
    {
        $legacy = DB::connection('legacy');
        $table = $type.'_data_1';

        if (! $legacy->getSchemaBuilder()->hasTable($table)) {
            return null;
        }

        $row = $legacy->table($table)->where('id', $id)->first();

        if ($row === null) {
            return null;
        }

        $name = $type === 'contact'
            ? trim(($row->f_first_name ?? '').' '.($row->f_last_name ?? ''))
            : trim((string) ($row->f_company_name ?? ''));

        return $name === '' ? null : $name;
    }
}
