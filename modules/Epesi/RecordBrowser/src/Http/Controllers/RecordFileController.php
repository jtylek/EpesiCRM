<?php

namespace Epesi\Modules\RecordBrowser\Http\Controllers;

use App\Models\StoredFile;
use Epesi\Modules\RecordBrowser\Files\StoredFileIds;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves one file of a record's file field (Field::file()). Files live in the
 * private file storage (App\Services\FileStorage), so this is the only way to
 * reach them, and it asks what the record's View page asks: may this user see
 * this record?
 *
 * The record is found through its model's own query, so the ownership scope
 * turns someone else's private record into a 404 — the same answer a record
 * that doesn't exist gets. A file is served only from a file column
 * (StoredFileIds) that holds it, so an id can't be read through a record it
 * doesn't belong to. A soft-deleted record stays reachable, as its View page
 * does, so Restore has something to show.
 */
class RecordFileController
{
    public function __invoke(Request $request, string $type, int $id, string $field, int $file): StreamedResponse
    {
        abort_unless(Auth::check(), 403);

        $model = Relation::getMorphedModel($type);

        abort_unless(is_string($model) && is_subclass_of($model, Model::class), 404);

        $query = $model::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        $record = $query->findOrFail($id);

        // No policy is Filament's "allowed" too: the scope has already had
        // its say on which records exist for this user.
        abort_unless(Gate::getPolicyFor($record) === null || Gate::allows('view', $record), 403);

        abort_unless(
            method_exists($record, 'fileColumns')
                && in_array($field, $record->fileColumns(), true)
                && in_array((string) $file, StoredFileIds::decode($record->getAttribute($field)), true),
            404,
        );

        $stored = StoredFile::query()->with('content')->find($file);

        abort_unless($stored?->isOnDisk(), 404);

        // "View": rendered in the browser rather than saved, for a type it's
        // safe to (isPreviewable()); anything else just downloads.
        $inline = $request->boolean('preview') && $stored->isPreviewable();

        return $stored->response(
            headers: ['X-Content-Type-Options' => 'nosniff'],
            disposition: $inline ? 'inline' : 'attachment',
        );
    }
}
