<?php

namespace Epesi\Modules\Attachments\LegacyImport;

use App\Enums\RecordPermission;
use App\Services\FileStorage;
use App\Services\LegacyImport\Importer;
use App\Services\LegacyImport\ImportSummary;
use App\Services\LegacyImport\LegacyIdMap;
use App\Services\LegacyImport\LegacyValue;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\ProjectsTickets\Models\Project;
use Epesi\Modules\ProjectsTickets\Models\Ticket;
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
 * ("premium_expense/1809", "company/5", sometimes several) — only the ones
 * this app has a model for (TARGETS below) can be linked. Roughly two thirds
 * of a real install's attachments point at recordsets not ported yet
 * (Premium Expenses, Invoice, Payments, Sales Opportunity, Knowledge Base,
 * Vacation, Vehicles) — those notes are still imported (title/note/files),
 * just left with no link, and counted in the summary rather than silently
 * dropped; there is nowhere to link them until those modules exist.
 *
 * `files` and `attached_to` are relation/derived data, not simple columns,
 * so — like every importer's pivots, and Mail's own links/attachments — they
 * are import-only: no history is replayed for them, matching Spatie's own
 * LogsActivity, which doesn't track pivot changes on the live app either.
 */
class AttachmentsImporter extends Importer
{
    /** Legacy recordset name (as it appears in `f_attached_to`) => this app's model. */
    protected const TARGETS = [
        'contact' => Contact::class,
        'company' => Company::class,
        'task' => Task::class,
        'crm_meeting' => Meeting::class,
        'phonecall' => PhoneCall::class,
        'premium_projects' => Project::class,
        'premium_tickets' => Ticket::class,
    ];

    /** @var array<class-string, LegacyIdMap> */
    protected array $maps = [];

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

        foreach (self::TARGETS as $class) {
            $this->maps[$class] = LegacyIdMap::for($class);
        }
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

        if ($this->dataDir === null) {
            $summary->warn('LEGACY_DATA_DIR is not set: attachment files are skipped (point it at the legacy install\'s data/ directory)');
        } elseif ($this->filesSkipped > 0) {
            $summary->warn("{$this->filesSkipped} file(s) not found in the legacy data directory, skipped");
        }

        foreach ($this->unportedTargets as $type => $count) {
            $summary->warn("{$count} attachment(s) attached to \"{$type}\", which isn't ported yet, left unlinked");
        }

        if ($this->unresolvedTargets > 0) {
            $summary->warn("{$this->unresolvedTargets} attachment link(s) pointed at a record that wasn't imported (yet) — rerun \"import:legacy attachments\" after \"projects\"/\"tickets\" have run, if this was a registration-order issue, not a genuine gap");
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
            'title' => ['title' => $raw],
            'note' => ['note' => $raw === null ? null : html_entity_decode($raw, ENT_QUOTES | ENT_HTML5)],
            'permission' => ['permission' => $raw !== null ? (int) $raw : RecordPermission::Public->value],
            'sticky' => ['sticky' => (bool) $raw],
            default => [],
        };
    }

    protected function extraAttributes(object $row): array
    {
        return ['files' => $this->importFiles($row)];
    }

    protected function syncPivots(object $row, Model $model): void
    {
        /** @var Attachment $model */
        $model->links()->delete();

        foreach (LegacyValue::typedRefMulti($row->f_attached_to ?? null) as $ref) {
            $class = self::TARGETS[$ref['type']] ?? null;

            if ($class === null) {
                $this->unportedTargets[$ref['type']] = ($this->unportedTargets[$ref['type']] ?? 0) + 1;

                continue;
            }

            $newId = $this->maps[$class]->get($ref['id']);

            if ($newId === null) {
                $this->unresolvedTargets++;

                continue;
            }

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
