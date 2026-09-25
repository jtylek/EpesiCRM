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
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): HtmlString => new HtmlString('<style>.epesi-note-editor .tiptap{min-height:40vh}</style>'),
            );
    }

    public function boot(Panel $panel): void {}
}
