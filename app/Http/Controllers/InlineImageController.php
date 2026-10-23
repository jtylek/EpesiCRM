<?php

namespace App\Http\Controllers;

use App\Models\StoredFile;
use App\Support\Files\InlineImages;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * An image pasted into a rich-text editor (App\Support\Files\InlineImages),
 * for whoever may view a record whose text shows it, or its uploader before
 * any record has claimed it. A guest gets 403; anyone else who may not see it
 * gets 404, the answer a file that doesn't exist gets, as a note's download
 * does. Only files that are such images: a note's file or an e-mail
 * attachment has no link and is never served here.
 *
 * Inline only for a type a browser renders without running anything from it
 * (StoredFile::isPreviewable(), never SVG); anything else downloads.
 */
class InlineImageController
{
    public function __invoke(int $storedFile, InlineImages $images): StreamedResponse
    {
        abort_unless(Auth::check(), 403);

        $file = StoredFile::query()->with('links')->find($storedFile);

        abort_unless($file !== null && $file->links->isNotEmpty() && $images->canView($file), 404);
        abort_unless($file->isOnDisk(), 404);

        $inline = $file->isPreviewable() && str_starts_with((string) $file->mimeType(), 'image/');

        return $file->response(
            headers: [
                'X-Content-Type-Options' => 'nosniff',
                // A StoredFile's bytes never change; private, as it was checked for this user.
                'Cache-Control' => 'private, max-age=86400',
            ],
            disposition: $inline ? 'inline' : 'attachment',
        );
    }
}
