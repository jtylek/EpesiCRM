<?php

namespace Epesi\Modules\Attachments\Http\Controllers;

use App\Models\StoredFile;
use Epesi\Modules\Attachments\Models\Attachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A note's `.html` file, served inline for the "View" action
 * (AttachmentResource::fileChip()) — same question as DownloadController: may
 * this user see this note?
 *
 * `.html` is deliberately outside StoredFile::isPreviewable()'s allow-list:
 * unlike a PDF or an image, a script tag in someone's uploaded HTML would run
 * in this app's own origin, with the viewer's session, if the browser were
 * left free to execute it. The `Content-Security-Policy: sandbox` header
 * closes that off the same way GitHub's raw HTML serving does — it forces an
 * opaque, cookie-less origin with scripts, forms and popups all disabled,
 * whether the file is opened directly or inside the preview iframe — so the
 * markup and its CSS still render without the file ever being able to act as
 * this app.
 */
class HtmlController
{
    public function __invoke(int $attachment, int $file): StreamedResponse
    {
        abort_unless(Auth::check(), 403);

        $note = Attachment::query()->findOrFail($attachment);

        abort_unless(Gate::allows('view', $note), 403);

        $stored = $note->storedFiles()->first(fn (StoredFile $candidate): bool => $candidate->getKey() === $file);

        abort_unless($stored?->isOnDisk(), 404);
        abort_unless(in_array(strtolower(pathinfo($stored->name, PATHINFO_EXTENSION)), ['html', 'htm'], true), 404);

        return $stored->response(
            headers: [
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => 'sandbox',
            ],
            disposition: 'inline',
        );
    }
}
