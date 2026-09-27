<?php

namespace Epesi\Modules\Attachments\Http\Controllers;

use App\Models\StoredFile;
use Epesi\Modules\Attachments\Models\Attachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * A note's `.md` file, rendered as HTML and opened in a new tab from the
 * "View" action next to it (AttachmentResource::fileChip()) — the same
 * question as DownloadController: may this user see this note?
 *
 * `html_input: strip` and `allow_unsafe_links: false` are load-bearing, not
 * defaults: a bare `Str::markdown()` call leaves league/commonmark's own
 * defaults in place (`html_input: allow`, `allow_unsafe_links: true`), which
 * would let a `<script>` or a `javascript:` link embedded in someone's
 * uploaded markdown run in this app's own origin, with the viewer's session
 * — the exact stored-XSS shape StoredFile::isPreviewable() already excludes
 * SVG for.
 */
class MarkdownController
{
    public function __invoke(int $attachment, int $file): View
    {
        abort_unless(Auth::check(), 403);

        $note = Attachment::query()->findOrFail($attachment);

        abort_unless(Gate::allows('view', $note), 403);

        $stored = $note->storedFiles()->first(fn (StoredFile $candidate): bool => $candidate->getKey() === $file);

        abort_unless($stored?->isOnDisk(), 404);
        abort_unless(in_array(strtolower(pathinfo($stored->name, PATHINFO_EXTENSION)), ['md', 'markdown'], true), 404);

        $html = Str::markdown((string) $stored->read(), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return view('attachments::markdown', [
            'title' => $stored->name,
            'html' => $html,
            'downloadUrl' => route('epesi.attachments.download', ['attachment' => $note->getKey(), 'file' => $stored->getKey()]),
        ]);
    }
}
