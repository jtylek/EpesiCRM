<?php

namespace App\Support\Files;

use App\Models\StoredFile;

/**
 * One file as a pill — icon, name (opens the preview when the type is safe to
 * render inline, else downloads it) and small View/Download icons after it —
 * Epesi's Utils_FileStorage_FileLeightbox popup as inline actions. Shared
 * between Notes (AttachmentResource, the original) and the Mail archive,
 * since both serve the same StoredFile model behind their own signed/plain
 * download routes and want the same look and click behaviour.
 *
 * A view opens in a pop-up over the page (file-preview-modal.blade.php picks
 * up every link marked data-file-preview), or in a new tab where there is no
 * pop-up to open. A download never gets a target="_blank": it has nothing to
 * show, so a new tab would only flash empty while the browser saves the file.
 */
class FileChip
{
    public static function render(StoredFile $file, string $downloadUrl, ?string $viewUrl, string $extraActionsHtml = ''): string
    {
        $preview = $viewUrl === null ? '' : sprintf(
            ' target="_blank" rel="noopener" data-file-preview data-kind="%s" data-name="%s" data-download="%s"',
            static::previewKind($file),
            e($file->name),
            e($downloadUrl),
        );

        $actions = $viewUrl === null
            ? ''
            : sprintf('<a href="%s"%s class="epesi-file-action" title="%s">%s</a>', e($viewUrl), $preview, e(__('View')), static::icon('heroicon-o-eye'));
        $actions .= sprintf('<a href="%s" class="epesi-file-action" title="%s">%s</a>', e($downloadUrl), e(__('Download')), static::icon('heroicon-o-arrow-down-tray'));
        $actions .= $extraActionsHtml;

        return sprintf(
            '<span class="epesi-file-chip">'
            .'<a href="%s"%s class="epesi-file-badge" title="%s">%s<span>%s</span></a>'
            .'<span class="epesi-file-actions">%s</span>'
            .'</span>',
            e($viewUrl ?? $downloadUrl),
            $preview,
            e($file->name),
            static::icon(static::fileIcon($file)),
            e($file->name),
            $actions,
        );
    }

    /**
     * "Get link": copies $url to the clipboard rather than navigating, since
     * the point is to hand it to someone else, not open it here. Confirms
     * the copy with Filament's own modal (link-copied-modal.blade.php,
     * MainPanelProvider) rather than a title tooltip — too easy to miss and
     * gone too fast to read — or a native alert(), which looks out of place
     * next to the rest of the app. There's no Livewire component here to
     * $dispatch through, so this opens it the same way Livewire would: a
     * plain "open-modal" CustomEvent on window, carrying the modal's id.
     */
    public static function shareButton(string $url): string
    {
        $title = __('Get link (valid for 7 days)');
        $onClick = "navigator.clipboard.writeText(this.dataset.url).then(()=>window.dispatchEvent(new CustomEvent('open-modal',{detail:{id:'epesi-link-copied'}})))";

        return sprintf(
            '<button type="button" class="epesi-file-action" title="%s" data-url="%s" onclick="%s">%s</button>',
            e($title),
            e($url),
            e($onClick),
            static::icon('heroicon-o-link'),
        );
    }

    /**
     * How the pop-up shows it: an image or a video in an element of its own,
     * scaled to fit (a frame shows them at full size, with scroll bars),
     * anything else — a PDF, text, rendered markdown — in a frame.
     */
    protected static function previewKind(StoredFile $file): string
    {
        $mime = (string) $file->mimeType();

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            default => 'frame',
        };
    }

    public static function icon(string $name): string
    {
        return svg($name, 'w-4 h-4')->toHtml();
    }

    public static function fileIcon(StoredFile $file): string
    {
        $mime = (string) $file->mimeType();

        return match (true) {
            str_starts_with($mime, 'image/') => 'heroicon-o-photo',
            str_starts_with($mime, 'video/') => 'heroicon-o-film',
            str_starts_with($mime, 'audio/') => 'heroicon-o-musical-note',
            $mime === 'application/pdf', str_starts_with($mime, 'text/') => 'heroicon-o-document-text',
            in_array($mime, ['application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed'], true) => 'heroicon-o-archive-box',
            default => 'heroicon-o-document',
        };
    }
}
