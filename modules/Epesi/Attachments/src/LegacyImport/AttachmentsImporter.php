<?php

namespace Epesi\Modules\Attachments\LegacyImport;

use App\Enums\RecordPermission;
use App\Services\FileStorage;
use App\Services\LegacyImport\Importer;
use App\Services\LegacyImport\ImportSummary;
use App\Services\LegacyImport\LegacyRecordRefs;
use App\Services\LegacyImport\LegacyValue;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\Attachments\Services\LegacyNoteCipher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * `import:legacy attachments`: the legacy `utils_attachment` recordset
 * (Epesi's generic file/note attachment, "Attached to" one or more records)
 * → Attachment, with its edit history, its files (read from the legacy data
 * directory's content-addressed Utils_FileStorage, exactly as CRM/Mail's own
 * attachments are — confirmed the layout matches App\Services\FileStorage's
 * own pathFor()) and its links.
 *
 * `f_attached_to` names the legacy recordset a note is attached to
 * ("company/5", sometimes several). A link resolves through whichever
 * importer brings that recordset over (LegacyRecordRefs), core or a module's,
 * so a module that imports a recordset gets its notes linked too. Notes
 * pointing at a recordset nothing imports are still imported (title/note/
 * files), just left with no link, and counted in the summary rather than
 * silently dropped.
 *
 * `files` and `attached_to` are relation/derived data, not simple columns,
 * so — like every importer's pivots, and Mail's own links/attachments — they
 * are import-only: no history is replayed for them, matching Spatie's own
 * LogsActivity, which doesn't track pivot changes on the live app either.
 */
class AttachmentsImporter extends Importer
{
    /**
     * What a legacy recordset name in `f_attached_to` became here: whatever
     * importer brings that recordset over, core or a module's.
     */
    protected LegacyRecordRefs $refs;

    protected ?string $dataDir;

    /** @var array<string, int> unported legacy recordset name => attachments referencing it */
    protected array $unportedTargets = [];

    protected int $filesSkipped = 0;

    protected int $unresolvedTargets = 0;

    public function __construct()
    {
        parent::__construct();

        $dir = config('epesi-attachments.legacy_data_dir');
        $this->dataDir = filled($dir) ? rtrim((string) $dir, '/\\') : null;

        $this->refs = LegacyRecordRefs::fromImporters();
    }

    public function legacyTab(): string
    {
        return 'utils_attachment';
    }

    public function modelClass(): string
    {
        return Attachment::class;
    }

    public function logName(): string
    {
        return 'attachment';
    }

    public function run(bool $withHistory = true): ImportSummary
    {
        if (! DB::connection('legacy')->getSchemaBuilder()->hasTable('utils_attachment_data_1')) {
            $summary = new ImportSummary;
            $summary->warn('the legacy database has no attachments (utils_attachment_data_1), nothing to import');

            return $summary;
        }

        $summary = parent::run($withHistory);

        $encryptedCount = Attachment::query()->where('legacy_encrypted', true)->count();
        if ($encryptedCount > 0) {
            $summary->warn("{$encryptedCount} legacy encrypted note(s) remain password-protected; ciphertext and password hints are retained. Install phpseclib/mcrypt_compat to enable decryption.");
        }

        if ($this->dataDir === null) {
            $summary->warn('LEGACY_DATA_DIR is not set: attachment files are skipped (point it at the legacy install\'s data/ directory)');
        } elseif ($this->filesSkipped > 0) {
            $summary->warn("{$this->filesSkipped} file(s) not found in the legacy data directory, skipped");
        }

        foreach ($this->unportedTargets as $type => $count) {
            $summary->warn("{$count} attachment(s) attached to \"{$type}\", which isn't ported yet, left unlinked");
        }

        if ($this->unresolvedTargets > 0) {
            $summary->warn("{$this->unresolvedTargets} attachment link(s) pointed at a record that wasn't imported (yet) — rerun \"import:legacy attachments\" once the records they're attached to are imported, if this was an ordering issue, not a genuine gap");
        }

        return $summary;
    }

    protected function trackedFields(): array
    {
        return [
            'title' => 'title',
            'note' => 'note',
            'permission' => 'permission',
            'sticky' => 'sticky',
        ];
    }

    protected function decodeTrackedValue(string $legacyField, ?string $raw): array
    {
        $raw = $raw === '' ? null : $raw;

        return match ($legacyField) {
            'title' => ['title' => $raw === null ? null : html_entity_decode($raw, ENT_QUOTES | ENT_HTML5)],
            'note' => $raw === null ? ['note' => null] : ['note' => html_entity_decode($raw, ENT_QUOTES | ENT_HTML5)],
            'permission' => ['permission' => $raw !== null ? (int) $raw : RecordPermission::Public->value],
            'sticky' => ['sticky' => (bool) $raw],
            default => [],
        };
    }

    protected function extraAttributes(object $row): array
    {
        $encrypted = (bool) ($row->f_crypted ?? false);
        $note = (string) ($row->f_note ?? '');

        // Imported before: an encrypted note keeps what it is here now, its
        // ciphertext or, decrypted since, its text. Writing the ciphertext
        // again would undo a decryption (and Attachment refuses it anyway).
        $existing = $encrypted ? Attachment::withTrashed()->where('legacy_id', $row->id)->first(['note', 'legacy_encrypted', 'legacy_password_hint']) : null;

        if ($existing !== null) {
            return [
                'files' => $this->importFiles($row),
                'legacy_encrypted' => $existing->legacy_encrypted,
                'legacy_password_hint' => $existing->legacy_password_hint,
                'note' => $existing->note,
            ];
        }

        // The hint is plain text legacy stored through htmlspecialchars() ("J&amp;S").
        $hint = $encrypted ? app(LegacyNoteCipher::class)->hint($note) : null;

        return [
            'files' => $this->importFiles($row),
            'legacy_encrypted' => $encrypted,
            'legacy_password_hint' => $hint === null ? null : html_entity_decode($hint, ENT_QUOTES | ENT_HTML5),
            'note' => $encrypted ? $note : ($note === '' ? null : html_entity_decode($note, ENT_QUOTES | ENT_HTML5)),
        ];
    }

    protected function syncPivots(object $row, Model $model): void
    {
        /** @var Attachment $model */
        $model->links()->delete();

        foreach (LegacyValue::typedRefMulti($row->f_attached_to ?? null) as $ref) {
            if (! $this->refs->ports($ref['type'])) {
                $this->unportedTargets[$ref['type']] = ($this->unportedTargets[$ref['type']] ?? 0) + 1;

                continue;
            }

            $newId = $this->refs->id($ref['type'], $ref['id']);

            if ($newId === null) {
                $this->unresolvedTargets++;

                continue;
            }

            $class = $this->refs->modelFor($ref['type']);
            $model->links()->create([
                'attachable_type' => (new $class)->getMorphClass(),
                'attachable_id' => $newId,
            ]);
        }
    }

    /**
     * @return list<string> StoredFile ids
     */
    protected function importFiles(object $row): array
    {
        $legacyFileIds = LegacyValue::multi($row->f_files ?? null);

        if ($legacyFileIds === [] || $this->dataDir === null) {
            return [];
        }

        $ids = [];

        foreach ($legacyFileIds as $legacyFileId) {
            $source = $this->legacyFile((int) $legacyFileId);

            if ($source === null) {
                $this->filesSkipped++;

                continue;
            }

            [$hash, $filename] = $source;
            $path = $this->dataDir.'/Utils_FileStorage/'.FileStorage::pathFor($hash);

            if (! is_file($path)) {
                $this->filesSkipped++;

                continue;
            }

            $stored = app(FileStorage::class)->putFile($path, $filename);
            $ids[] = (string) $stored->getKey();
        }

        return $ids;
    }

    /**
     * @return array{0: string, 1: string}|null [hash, filename]
     */
    protected function legacyFile(int $legacyFileId): ?array
    {
        $row = DB::connection('legacy')->table('utils_filestorage as s')
            ->join('utils_filestorage_files as f', 'f.id', '=', 's.file_id')
            ->where('s.id', $legacyFileId)
            ->first(['s.filename', 'f.hash']);

        return $row ? [$row->hash, $row->filename] : null;
    }
}
