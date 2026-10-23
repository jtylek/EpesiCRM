<?php

namespace App\Support\Files;

use App\Models\Concerns\HasInlineImages;
use App\Models\StoredFile;
use App\Models\StoredFileLink;
use App\Services\FileStorage;
use Carbon\CarbonInterface;
use Closure;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\RichContentAttribute;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Images pasted or dropped into a rich-text (or Markdown) editor. They go to
 * the file storage like any other file (App\Services\FileStorage), and the
 * text keeps only the StoredFile's id: Filament's `<img data-id="12">`, with
 * no `src`. The address is added whenever the text is shown (render(), the
 * editor's getFileAttachmentUrlUsing()), and it is the `epesi.files.inline`
 * route, which serves the image only to someone who may view a record whose
 * text holds it (canView()). A Markdown editor has no ids, so its text holds
 * that route's address, which render() rewrites to this installation's.
 *
 * Which record holds which image is the `stored_file_links` table. An upload
 * gets a link with no owner, which only its uploader can see; saving the
 * record (HasInlineImages) gives the link its owner. An image taken out of
 * the text stays linked, since History can still show and restore the text
 * that had it. A record deleted for good releases its images (release()), and
 * the daily prune() removes uploads no record claimed and links whose record
 * is gone.
 *
 * Also a FileAttachmentProvider, so Filament's RichContentRenderer resolves
 * the same addresses.
 */
class InlineImages implements FileAttachmentProvider
{
    public const ROUTE = 'epesi.files.inline';

    /**
     * The defaults every rich and Markdown editor gets (AppServiceProvider).
     * An editor takes images only on a form of a model that keeps track of
     * them; anywhere else (a sticky note) an image would have no record to
     * belong to. An editor's own fileAttachments() still wins: a mail being
     * composed takes them, as its uploader's until the daily prune, since
     * the sent message carries its own copies (replace()).
     */
    public static function configure(RichEditor|MarkdownEditor $editor): RichEditor|MarkdownEditor
    {
        // Private: a rich editor then drops an image's `src` from the saved
        // text and asks for the address again each time it loads it. A
        // Markdown editor has no ids and writes the address into the text.
        if ($editor instanceof RichEditor) {
            $editor->fileAttachmentsVisibility('private');
        }

        return $editor
            ->fileAttachments(fn (RichEditor|MarkdownEditor $component): bool => static::supports($component->getModel()))
            ->saveUploadedFileAttachmentUsing(fn (TemporaryUploadedFile $file): string => (string) app(static::class)->store($file)->getKey())
            ->getFileAttachmentUrlUsing(fn (mixed $file): ?string => app(static::class)->url($file));
    }

    /** Whether records of this model keep track of their images. */
    public static function supports(?string $model): bool
    {
        return $model !== null
            && class_exists($model)
            && in_array(HasInlineImages::class, class_uses_recursive($model), true);
    }

    /** An upload, stored and linked to nobody yet: its uploader's until a record claims it. */
    public function store(UploadedFile $file): StoredFile
    {
        $stored = app(FileStorage::class)->putUpload($file);
        $stored->links()->create();

        return $stored;
    }

    /** The image's address, for a StoredFile id; null for anything else. */
    public function url(mixed $id): ?string
    {
        return is_scalar($id) && ctype_digit((string) $id) && (int) $id > 0
            ? route(self::ROUTE, ['storedFile' => (int) $id])
            : null;
    }

    /**
     * The text ready to show: each image of the file storage gets its address,
     * whether the text names it by id (`data-id`) or by an address of the
     * image route written into it (Markdown, or a copy from another page).
     */
    public function render(?string $html): string
    {
        $html = (string) $html;

        if (! str_contains($html, '<img')) {
            return $html;
        }

        return preg_replace_callback('~<img\b[^>]*>~i', function (array $match): string {
            $tag = $match[0];
            $id = match (true) {
                (bool) preg_match('~\sdata-id\s*=\s*(["\']?)(\d+)\1~i', $tag, $byId) => $byId[2],
                (bool) preg_match('~\ssrc\s*=\s*(["\'])[^"\']*/files/inline/(\d+)(?!\d)[^"\']*\1~i', $tag, $bySrc) => $bySrc[2],
                default => null,
            };

            if ($id === null) {
                return $tag;
            }

            $tag = (string) preg_replace('~\ssrc\s*=\s*(["\']).*?\1~is', '', $tag);

            return (string) preg_replace('~^<img\b~i', '<img src="'.e((string) $this->url($id)).'"', $tag, 1);
        }, $html) ?? $html;
    }

    /**
     * The text for a copy that leaves this installation, such as an e-mail:
     * each image of the file storage that $user may see gets the `src`
     * $source returns for it (the e-mail's `cid:`). Any other image of the
     * file storage, an SVG, or one whose file is gone, is dropped, so an id
     * typed into the text can't send out someone else's image.
     *
     * @param  Closure(StoredFile): string  $source
     */
    public function replace(?string $html, Authenticatable $user, Closure $source): string
    {
        $html = (string) $html;

        if (! str_contains($html, '<img')) {
            return $html;
        }

        return preg_replace_callback('~<img\b[^>]*>~i', function (array $match) use ($user, $source): string {
            $tag = $match[0];
            $id = $this->ids($tag)[0] ?? null;

            if ($id === null) {
                return $tag;
            }

            $file = StoredFile::query()->with(['links', 'content'])->find($id);

            if ($file === null || ! $file->isPreviewable() || ! str_starts_with((string) $file->mimeType(), 'image/')
                || ! $this->canView($file, $user) || ! $file->isOnDisk()) {
                return '';
            }

            $tag = (string) preg_replace('~\s(?:src|data-id)\s*=\s*(["\']).*?\1~is', '', $tag);

            return (string) preg_replace('~^<img\b~i', '<img src="'.e($source($file)).'"', $tag, 1);
        }, $html) ?? $html;
    }

    /**
     * The StoredFile ids of the images a text shows.
     *
     * @return list<int>
     */
    public function ids(?string $text): array
    {
        $text = (string) $text;
        $ids = [];

        if (preg_match_all('~<img\b[^>]*>~i', $text, $tags)) {
            foreach ($tags[0] as $tag) {
                if (preg_match('~\sdata-id\s*=\s*(["\']?)(\d+)\1~i', $tag, $match)) {
                    $ids[] = (int) $match[2];
                }
            }
        }

        if (preg_match_all('~/files/inline/(\d+)(?!\d)~', $text, $matches)) {
            array_push($ids, ...array_map('intval', $matches[1]));
        }

        return array_values(array_unique($ids));
    }

    /**
     * Whether an editor's state (Filament's JSON document, HTML or Markdown)
     * shows an image.
     */
    public static function hasImages(mixed $state): bool
    {
        if (is_array($state)) {
            if (($state['type'] ?? null) === 'image') {
                return true;
            }

            foreach ($state as $value) {
                if (is_array($value) && static::hasImages($value)) {
                    return true;
                }
            }

            return false;
        }

        return is_string($state) && preg_match('~<img\b|!\[[^\]]*\]\(~i', $state) === 1;
    }

    /**
     * Links the images $owner's text shows to it. An image is taken only if
     * it is already this record's, an upload of the signed-in user no record
     * has claimed, or already shown by a record the user may see (pasted from
     * it). An id typed into the text for anything else is left unlinked, so it
     * shows nothing.
     *
     * @param  Model&HasInlineImages  $owner
     */
    public function sync(Model $owner): void
    {
        $ids = [];

        foreach ($owner->richTextColumns() as $column) {
            $value = $owner->getAttribute($column);
            array_push($ids, ...(is_scalar($value) ? $this->ids((string) $value) : []));
        }

        if ($ids === []) {
            return;
        }

        $type = $owner->getMorphClass();
        $key = (string) $owner->getKey();
        $userId = Auth::id();

        foreach (StoredFile::query()->with('links')->whereKey(array_unique($ids))->get() as $file) {
            if ($file->links->contains(fn (StoredFileLink $link): bool => $link->owner_type === $type && (string) $link->owner_id === $key)) {
                continue;
            }

            $pending = $file->links->first(fn (StoredFileLink $link): bool => $link->isPending());

            if ($pending && $userId !== null && (int) $file->created_by === (int) $userId) {
                $pending->update(['owner_type' => $type, 'owner_id' => $owner->getKey()]);

                continue;
            }

            if ($file->links->contains(fn (StoredFileLink $link): bool => ! $link->isPending()) && $this->canView($file)) {
                $this->link($file, $owner);
            }
        }
    }

    /** One more record whose text shows the image. Idempotent. */
    public function link(StoredFile $file, Model $owner): void
    {
        StoredFileLink::query()->firstOrCreate([
            'stored_file_id' => $file->getKey(),
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);
    }

    /**
     * $owner is gone for good: its links go, and every image no other record
     * shows goes with them.
     */
    public function release(Model $owner): void
    {
        $links = StoredFileLink::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->get(['id', 'stored_file_id']);

        if ($links->isEmpty()) {
            return;
        }

        StoredFileLink::query()->whereKey($links->modelKeys())->delete();
        $this->deleteUnlinked($links->pluck('stored_file_id')->all());
    }

    /**
     * May $user (by default the signed-in one) see this image: its uploader while no record has
     * claimed it, afterwards whoever may view a record whose text shows it.
     * Each record is loaded through its own query, so its ownership scope
     * applies (someone else's private record doesn't load), then asked the
     * `view` policy. A record type with no policy is visible to nobody but a
     * super admin.
     */
    public function canView(StoredFile $file, ?Authenticatable $user = null): bool
    {
        $user ??= Auth::user();

        if ($user === null) {
            return false;
        }

        foreach ($file->links as $link) {
            if ($link->isPending()) {
                if ((int) $file->created_by === (int) $user->getAuthIdentifier()) {
                    return true;
                }

                continue;
            }

            $class = Relation::getMorphedModel($link->owner_type);

            if ($class === null || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $owner = $class::query()->find($link->owner_id);

            if ($owner !== null && Gate::forUser($user)->allows('view', $owner)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The daily clean-up: uploads no record claimed before $before, and links
     * whose record no longer exists (deleted without its model's events). A
     * trashed record still exists. A record type whose module is off is left
     * alone: its records come back with it.
     *
     * @return array{pending: int, orphaned: int, files: int}
     */
    public function prune(CarbonInterface $before): array
    {
        $pending = StoredFileLink::query()
            ->whereNull('owner_type')
            ->where('created_at', '<', $before)
            ->get(['id', 'stored_file_id']);
        StoredFileLink::query()->whereKey($pending->modelKeys())->delete();
        $fileIds = $pending->pluck('stored_file_id')->all();
        $orphaned = 0;

        foreach (StoredFileLink::query()->whereNotNull('owner_type')->distinct()->pluck('owner_type') as $type) {
            $class = Relation::getMorphedModel($type);

            if ($class === null || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $model = new $class;

            if (! Schema::hasTable($model->getTable())) {
                continue;
            }

            $gone = StoredFileLink::query()
                ->where('owner_type', $type)
                ->whereNotIn('owner_id', $model->newQueryWithoutScopes()->select($model->getKeyName())->toBase())
                ->get(['id', 'stored_file_id']);
            StoredFileLink::query()->whereKey($gone->modelKeys())->delete();
            array_push($fileIds, ...$gone->pluck('stored_file_id')->all());
            $orphaned += $gone->count();
        }

        return ['pending' => $pending->count(), 'orphaned' => $orphaned, 'files' => $this->deleteUnlinked($fileIds)];
    }

    /**
     * Deletes those of these StoredFiles that no record shows any more; the
     * file storage then drops a content no other file uses.
     *
     * @param  array<int, int|string>  $ids
     */
    protected function deleteUnlinked(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $files = StoredFile::query()->whereKey(array_unique($ids))->whereDoesntHave('links')->get();
        $files->each->delete();

        return $files->count();
    }

    // FileAttachmentProvider, for RichContentRenderer.

    public function attribute(RichContentAttribute $attribute): static
    {
        return $this;
    }

    public function getFileAttachmentUrl(mixed $file): ?string
    {
        return $this->url($file);
    }

    public function saveUploadedFileAttachment(TemporaryUploadedFile $file): mixed
    {
        return (string) $this->store($file)->getKey();
    }

    public function getDefaultFileAttachmentVisibility(): ?string
    {
        return 'private';
    }

    public function isExistingRecordRequiredToSaveNewFileAttachments(): bool
    {
        return false;
    }

    /**
     * Never at save: History may still show an image taken out of the text
     * (see release() and prune()).
     *
     * @param  array<mixed>  $exceptIds
     */
    public function cleanUpFileAttachments(array $exceptIds): void {}
}
