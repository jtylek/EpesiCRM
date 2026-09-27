<?php

namespace Epesi\Modules\Attachments\Http\Controllers;

use App\Models\StoredFile;
use Epesi\Modules\Attachments\Models\Attachment;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a note's file from a "Get link" URL — legacy's Utils_FileStorage
 * remote.php: a link good for whoever holds it, logged in or not, for as
 * long as it's valid, for pasting into an e-mail or handing to someone
 * outside the CRM. The route's `signed` middleware is the only gate; unlike
 * DownloadController this asks neither Auth::check() nor Gate::allows(),
 * deliberately, since the whole point is that it works without either.
 *
 * Attachment's HasOwnershipVisibility scope adds no constraint for a guest
 * (Auth::user() is null), so this reaches a Private note's files same as a
 * Public one's — the note's own permission never gated file access to begin
 * with (DownloadController itself asks only "may you view the note", not
 * "is it Public"), it's simply that only Gate::allows('view') callers could
 * reach this far before. Generating the link is what's gated: it's only ever
 * built into a page the viewer already had permission to see.
 */
class SharedFileController
{
    public function __invoke(int $attachment, int $file): StreamedResponse
    {
        $note = Attachment::query()->findOrFail($attachment);

        $stored = $note->storedFiles()->first(fn (StoredFile $candidate): bool => $candidate->getKey() === $file);

        abort_unless($stored?->isOnDisk(), 404);

        return $stored->response(
            headers: ['X-Content-Type-Options' => 'nosniff'],
            disposition: $stored->isPreviewable() ? 'inline' : 'attachment',
        );
    }
}
