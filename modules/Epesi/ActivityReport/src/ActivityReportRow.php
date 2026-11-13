<?php

namespace Epesi\Modules\ActivityReport;

use App\Support\RelativeUrl;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\RecordBrowser\Recordset\LinkableRecordsets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

/**
 * What one report line says about its record: the type, the title and link,
 * and whether the viewer may see the record at all. A record the viewer may
 * not view is "Access restricted" with no link and no change details, as in
 * Epesi; one that no longer exists is told so.
 *
 * A note or file entry reports the record it is attached to, not the
 * attachment.
 */
class ActivityReportRow
{
    /** @var array<int, array{alias: string, title: ?string, url: ?string, state: string}> */
    private static array $memo = [];

    /**
     * @return array{alias: string, title: ?string, url: ?string, state: string} state: ok | restricted | removed
     */
    public static function for(Activity $entry): array
    {
        return self::$memo[$entry->getKey()] ??= self::resolve($entry);
    }

    public static function forget(): void
    {
        self::$memo = [];
    }

    /** The entry's action as the report words it. */
    public static function action(Activity $entry): string
    {
        $files = $entry->subject_type === ActivityReport::attachmentAlias();

        return match (true) {
            $files => __('Note or file').': '.($entry->event === 'created' ? __('New') : __('Updated')),
            in_array($entry->event, ['created', 'cloned'], true) => __('Created'),
            $entry->event === 'deleted' => __('Deleted'),
            $entry->event === 'restored' => __('Restored'),
            default => __('Edited'),
        };
    }

    private static function resolve(Activity $entry): array
    {
        $alias = (string) $entry->subject_type;
        $id = $entry->subject_id;

        if ($alias === ActivityReport::attachmentAlias()) {
            $attachment = self::find($alias, $id);

            // What an attachment is attached to (its first link) is the
            // record the report names.
            $link = $attachment instanceof Attachment ? $attachment->links()->orderBy('id')->first() : null;

            if ($link === null) {
                return self::result($alias, null, null, $attachment === null ? self::absence($alias, $id) : 'removed');
            }

            [$alias, $id] = [$link->attachable_type, $link->attachable_id];
        }

        $record = self::find($alias, $id);

        if ($record === null) {
            return self::result($alias, null, null, self::absence($alias, $id));
        }

        if (! Gate::allows('view', $record)) {
            return self::result($alias, null, null, 'restricted');
        }

        $resource = LinkableRecordsets::resource($alias);
        $title = $resource ? strip_tags((string) $resource::getRecordTitle($record)) : '';
        $url = $resource && $resource::hasPage('view')
            ? RelativeUrl::of($resource::getUrl('view', ['record' => $record], panel: 'main'))
            : null;

        return self::result($alias, filled($title) ? $title : '#'.$record->getKey(), $url, 'ok');
    }

    /** The record as the viewer sees it: ownership scopes apply, trashed ones included. */
    private static function find(string $alias, mixed $id): ?Model
    {
        $class = Relation::getMorphedModel($alias);

        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        $query = $class::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
            $query->withTrashed();
        }

        return $query->find($id);
    }

    /** Hidden from the viewer by a scope, or gone altogether. */
    private static function absence(string $alias, mixed $id): string
    {
        $class = Relation::getMorphedModel($alias);

        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            return 'removed';
        }

        $query = $class::query()->withoutGlobalScopes();

        return $query->whereKey($id)->exists() ? 'restricted' : 'removed';
    }

    private static function result(string $alias, ?string $title, ?string $url, string $state): array
    {
        return ['alias' => $alias, 'title' => $title, 'url' => $url, 'state' => $state];
    }
}
