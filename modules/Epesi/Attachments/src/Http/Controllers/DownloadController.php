<?php

namespace Epesi\Modules\Attachments\Http\Controllers;

use App\Models\StoredFile;
use Epesi\Modules\Attachments\Models\Attachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves one file of a note. Files live in the private file storage (the port
 * of Epesi's `.htaccess: deny from all` data directory, see
 * App\Services\FileStorage), so this is the only way to reach them, and it
 * asks the same question the Notes tab does: may this user see this note? The
 * model's ownership scope already hides someone else's private note, which
 * makes it a 404 rather than a 403 — the same answer a note that does not
 * exist gets. A file is only served through a note that holds it.
 */
class DownloadController
{
    public function __invoke(int $attachment, int $file): StreamedResponse
    {
        abort_unless(Auth::check(), 403);

        $note = Attachment::query()->findOrFail($attachment);

        abort_unless(Gate::allows('view', $note), 403);

        $stored = $note->storedFiles()->first(fn (StoredFile $candidate): bool => $candidate->getKey() === $file);

        abort_unless($stored?->isOnDisk(), 404);

        return $stored->download();
    }
}
