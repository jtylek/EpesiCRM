{{--
    A stylesheet rather than Tailwind utilities: the panel uses Filament's
    precompiled CSS, which has no classes a module adds. Filament stacks a
    tags input's tags under its text box; an address line reads better with
    the tags and the text box on one line.
--}}
<style>
    .epesi-compose-address .fi-fo-tags-input [x-data] {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
    }

    .epesi-compose-address .fi-fo-tags-input [wire\:ignore] {
        display: contents;
    }

    .epesi-compose-address .fi-fo-tags-input-tags-ctn {
        order: -1;
        width: auto;
        border-top: 0;
        padding-block: 0.25rem;
        padding-inline: 0.5rem 0;
    }

    .epesi-compose-address .fi-fo-tags-input input.fi-input {
        flex: 1 1 8rem;
        width: auto;
        min-width: 8rem;
    }

    {{--
        A starting size that still leaves Attachments in view, not the
        26rem this used to be. It still grows with the message on its own
        (nothing below caps its height), and the drag handle (needs its own
        overflow, or the browser won't show one) lets the sender make more
        room up front — after a manual drag it scrolls past that height
        instead of growing further, same as any resizable textarea.
    --}}
    .epesi-compose-body .fi-fo-rich-editor-content .tiptap {
        min-height: 12rem;
        overflow-y: auto;
        resize: vertical;
    }
</style>
