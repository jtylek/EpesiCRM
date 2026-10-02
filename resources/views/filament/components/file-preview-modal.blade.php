{{--
    A file's preview in a pop-up over the page (FileChip's View link and file
    name, for Notes and Mail attachments), instead of a new tab — which an
    installed epesi, having no tab strip, opens as a new window. Epesi's
    Utils_FileStorage_FileLeightbox, with a button to fill the whole window.

    One listener for every link marked data-file-preview, so the chips carry
    no script of their own and a page without this pop-up just follows the
    link. A click with Ctrl, Shift or the middle button still opens a tab, and
    phones keep opening the file itself: their browsers mostly can't show a
    PDF inside a page. Leaving the pop-up clears the frame, which stops a
    video or sound.

    An image or a video (data-kind, FileChip) gets an element of its own,
    shrunk to fit and never enlarged past its own size: in a frame it would
    show at full size with scroll bars. Maximized, it fills the window as far
    as its size allows.

    The header's links get their address from Alpine, so "Open separately"
    binds its target too: the icon button drops its own target prop when it
    has no href to render.
--}}
<div
    x-data="{
        url: null, kind: 'frame', name: '', download: '', maximized: false, touch: false,
        async renderPdf(pages) {
            // Phone browsers download a PDF instead of showing it in a frame,
            // so draw its pages with pdf.js (bundled in public/vendor/pdfjs: the legacy
            // build, which also runs on browsers a few years old).
            const url = this.url;
            pages.replaceChildren();
            try {
                const pdfjs = await import('{{ asset('vendor/pdfjs/pdf.min.mjs') }}?v={{ @filemtime(public_path('vendor/pdfjs/pdf.min.mjs')) }}');
                pdfjs.GlobalWorkerOptions.workerSrc = '{{ asset('vendor/pdfjs/pdf.worker.min.mjs') }}?v={{ @filemtime(public_path('vendor/pdfjs/pdf.worker.min.mjs')) }}';
                const pdf = await pdfjs.getDocument({ url, withCredentials: true }).promise;
                for (let n = 1; n <= pdf.numPages && this.url === url; n++) {
                    const page = await pdf.getPage(n);
                    const viewport = page.getViewport({ scale: (pages.clientWidth / page.getViewport({ scale: 1 }).width) * (window.devicePixelRatio || 1) });
                    const canvas = document.createElement('canvas');
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    canvas.style.cssText = 'width:100%;margin-bottom:.5rem;background:#fff';
                    pages.appendChild(canvas);
                    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
                }
            } catch (e) {
                console.error(e);
                // The reason too (a browser too old for pdf.js, a refused download, ...), small,
                // so a failure on someone's phone can be told apart from another.
                pages.textContent = @js(__('This file could not be previewed.'));
                const reason = document.createElement('small');
                reason.style.cssText = 'display:block;margin-top:.5rem;opacity:.7;word-break:break-word';
                reason.textContent = (e && (e.name ? e.name + ': ' : '') + (e.message || e)) || '';
                pages.appendChild(reason);
            }
        },
    }"
    x-on:click.window="
        const link = $event.target.closest('a[data-file-preview]');
        if (! link || $event.button !== 0 || $event.ctrlKey || $event.metaKey || $event.shiftKey || $event.altKey) return;
        if (['frame'].includes(link.dataset.kind) && window.matchMedia('(pointer: coarse)').matches) return;
        touch = window.matchMedia('(pointer: coarse)').matches;
        $event.preventDefault();
        url = link.href;
        kind = link.dataset.kind;
        name = link.dataset.name;
        download = link.dataset.download;
        maximized = false;
        $dispatch('open-modal', { id: 'epesi-file-preview' });
    "
    x-on:close-modal.window="if ($event.detail.id === 'epesi-file-preview') url = null"
>
    <style>
        .epesi-file-preview>.fi-modal-window-ctn{padding:.5rem}
        .epesi-file-preview .fi-modal-header{padding:.5rem .75rem 0}
        .epesi-file-preview .fi-modal-content{padding:.5rem .75rem .75rem}
        .epesi-file-preview .fi-modal-close-btn{top:.5rem;inset-inline-end:.75rem}
        .epesi-file-preview-header{display:flex;align-items:center;gap:.5rem;width:100%;min-width:0;padding-inline-end:2.5rem}
        .epesi-file-preview-header .fi-modal-heading{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .epesi-file-preview-frame{display:block;width:100%;height:75dvh;border:0;border-radius:.5rem;background:#fff}
        .fi-modal>.fi-modal-window-ctn:has(>.epesi-file-preview-max){padding:0}
        .fi-modal>.fi-modal-window-ctn>.fi-modal-window.epesi-file-preview-max{max-width:none;height:100dvh;border-radius:0;grid-row:1/-1}
        .epesi-file-preview-max .fi-modal-content{flex:1;min-height:0}
        .epesi-file-preview-max .epesi-file-preview-frame{flex:1;height:auto}
        .epesi-file-preview-media{display:block;margin-inline:auto;max-width:100%;max-height:75dvh;border-radius:.5rem}
        .epesi-file-preview-max .epesi-file-preview-media{flex:1 1 0;min-height:0;width:100%;max-height:none;object-fit:scale-down}
    </style>

    <x-filament::modal
        id="epesi-file-preview"
        class="epesi-file-preview"
        width="7xl"
        :extra-modal-window-attribute-bag="new \Filament\Support\View\ComponentAttributeBag(['x-bind:class' => '{ \'epesi-file-preview-max\': maximized }'])"
    >
        <x-slot name="header">
            <div class="epesi-file-preview-header">
                <h2 class="fi-modal-heading" x-text="name"></h2>

                <x-filament::icon-button
                    color="gray"
                    icon="heroicon-o-arrow-down-tray"
                    :label="__('Download')"
                    :tooltip="__('Download')"
                    tag="a"
                    x-bind:href="download"
                />

                <x-filament::icon-button
                    color="gray"
                    icon="heroicon-o-arrow-top-right-on-square"
                    :label="__('Open separately')"
                    :tooltip="__('Open separately')"
                    tag="a"
                    rel="noopener"
                    x-bind:target="'_blank'"
                    x-bind:href="url"
                />

                <x-filament::icon-button
                    color="gray"
                    icon="heroicon-o-arrows-pointing-out"
                    :label="__('Maximize')"
                    :tooltip="__('Maximize')"
                    x-show="! maximized"
                    x-on:click="maximized = true"
                />

                <x-filament::icon-button
                    color="gray"
                    icon="heroicon-o-arrows-pointing-in"
                    :label="__('Restore size')"
                    :tooltip="__('Restore size')"
                    x-cloak
                    x-show="maximized"
                    x-on:click="maximized = false"
                />
            </div>
        </x-slot>

        <template x-if="url && kind === 'image'">
            <img class="epesi-file-preview-media" x-bind:src="url" x-bind:alt="name">
        </template>

        <template x-if="url && kind === 'video'">
            <video class="epesi-file-preview-media" x-bind:src="url" controls></video>
        </template>

        <template x-if="url && kind === 'pdf' && touch">
            <div class="epesi-file-preview-pdf" x-init="$nextTick(() => renderPdf($el))"></div>
        </template>

        <template x-if="url && kind === 'frame' || url && kind === 'pdf' && ! touch">
            <iframe class="epesi-file-preview-frame" x-bind:src="url" x-bind:title="name"></iframe>
        </template>
    </x-filament::modal>
</div>
