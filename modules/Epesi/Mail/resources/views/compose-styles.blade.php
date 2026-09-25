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

    .epesi-compose-body .fi-fo-rich-editor-content .tiptap {
        min-height: 26rem;
    }
</style>
