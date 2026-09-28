<?php

namespace Epesi\Modules\Attachments;

use Epesi\Modules\Attachments\Filament\Resources\Attachments\AttachmentResource;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;

class AttachmentsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-attachments';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([AttachmentResource::class])
            // Filament's editor is one line tall until typed into; on the
            // note page it should read as a writing area from the start.
            // Each file is a pill (icon + name, opening it) with its own
            // small View/Download/Get link icons after it — Epesi's
            // Utils_FileStorage_FileLeightbox popup as inline actions.
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): HtmlString => new HtmlString('<style>'
                    .'.epesi-note-editor .tiptap{min-height:40vh}'
                    // "Attached to" carries its own warning in its label line
                    // (AttachmentResource::attachedToLabel()), so the field's
                    // message under the table would say it twice.
                    .'.epesi-attach-to>.fi-fo-field-content-col>.fi-fo-field-wrp-error-message{display:none}'
                    .'.epesi-attach-to-error{margin-inline-start:.5rem;font-weight:400;color:var(--danger-600)}'
                    .'.dark .epesi-attach-to-error{color:var(--danger-400)}'
                    .'.epesi-note-toggle{cursor:pointer}'
                    .'.epesi-note-full-title{font-weight:600;margin-bottom:.25rem}'
                    .'.epesi-note-meta{margin-top:.5rem;display:flex;flex-direction:column;gap:.25rem;font-size:.75rem;color:var(--gray-500)}'
                    .'.dark .epesi-note-meta{color:var(--gray-400)}'
                    .'.epesi-note-meta-label{font-weight:600;margin-inline-end:.375rem}'
                    .'.epesi-note-meta-by{color:var(--gray-400)}'
                    .'.epesi-note-meta-chip{display:inline-flex;align-items:center;gap:.25rem;margin-inline-end:.25rem;padding:.0625rem .5rem;border-radius:9999px;background:var(--gray-50);box-shadow:inset 0 0 0 1px var(--gray-300);color:inherit;text-decoration:none;vertical-align:middle}'
                    .'.dark .epesi-note-meta-chip{background:color-mix(in oklab,var(--gray-500) 15%,transparent);box-shadow:inset 0 0 0 1px color-mix(in oklab,var(--gray-400) 35%,transparent)}'
                    .'.epesi-note-meta-chip:hover{color:var(--primary-600)}'
                    .'.epesi-note-meta-chip svg{width:.75rem;height:.75rem}'
                    // The colours of Filament's primary badge, as the "Linked
                    // to" badges have (LinkedRecords), so a file stands out
                    // as something to click in the same way.
                    .'.epesi-file-chip{display:inline-flex;align-items:center;gap:.375rem;margin:.125rem;padding:.125rem .5rem .125rem .375rem;border-radius:9999px;background:var(--primary-50);box-shadow:inset 0 0 0 1px color-mix(in oklab,var(--primary-600) 10%,transparent);color:var(--primary-700);font-size:.75rem;font-weight:500;line-height:1.25rem;vertical-align:middle}'
                    .'.dark .epesi-file-chip{background:color-mix(in oklab,var(--primary-400) 10%,transparent);box-shadow:inset 0 0 0 1px color-mix(in oklab,var(--primary-400) 30%,transparent);color:var(--primary-400)}'
                    .'.epesi-file-badge{display:inline-flex;align-items:center;gap:.375rem;color:inherit;text-decoration:none}'
                    .'.epesi-file-badge:hover span{text-decoration:underline}'
                    .'.epesi-file-badge svg{width:1rem;height:1rem;flex-shrink:0}'
                    .'.epesi-file-actions{display:inline-flex;align-items:center;gap:.25rem;margin-inline-start:.25rem;padding-inline-start:.375rem;border-inline-start:1px solid color-mix(in oklab,var(--primary-600) 20%,transparent)}'
                    .'.dark .epesi-file-actions{border-inline-start-color:color-mix(in oklab,var(--primary-400) 30%,transparent)}'
                    .'.epesi-file-action{display:inline-flex;padding:0;border:0;background:none;color:color-mix(in oklab,var(--primary-700) 55%,transparent);cursor:pointer}'
                    .'.epesi-file-action:hover{color:var(--primary-700)}'
                    .'.dark .epesi-file-action{color:color-mix(in oklab,var(--primary-400) 60%,transparent)}'
                    .'.dark .epesi-file-action:hover{color:var(--primary-300)}'
                    .'.epesi-file-action svg{width:1rem;height:1rem}'
                    .'</style>'),
            );
    }

    public function boot(Panel $panel): void {}
}
