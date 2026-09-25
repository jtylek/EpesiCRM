<?php

namespace Epesi\Modules\Mail\Http\Controllers;

use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a stored attachment of an archived message — CRM/Mail's get.php —
 * to anyone who may view the message. `?inline=1` is how the sandboxed body
 * viewer shows embedded images (via a signed URL, see MailAttachment::url()),
 * and is only honoured for raster images, so a crafted link can't make the
 * browser render an attached HTML or SVG file.
 */
class AttachmentController
{
    public function __invoke(Request $request, int $mail, int $attachment): StreamedResponse
    {
        // A valid signature (issued only while rendering a message to someone
        // allowed to see it) stands in for the session, which the sandboxed
        // body viewer cannot send; everything else needs a logged-in viewer.
        $signed = $request->hasValidSignature();

        abort_unless($signed || Auth::check(), 403);

        $message = Mail::query()->findOrFail($mail);
        abort_unless($signed || Gate::allows('view', $message), 403);

        /** @var MailAttachment $file */
        $file = $message->attachments()->with('storedFile.content')->findOrFail($attachment);
        abort_unless($file->storedFile?->isOnDisk(), 404);

        $inline = $request->boolean('inline') && str_starts_with((string) $file->mime_type, 'image/')
            && $file->mime_type !== 'image/svg+xml';

        return $file->storedFile->response($file->name, [
            'Content-Type' => $inline ? $file->mime_type : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ], $inline ? 'inline' : 'attachment');
    }
}
