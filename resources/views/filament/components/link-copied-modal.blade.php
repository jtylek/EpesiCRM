{{--
    A small alert for FileChip's "Get link" button (Notes and Mail
    attachments): copying the link to the clipboard is silent, so this
    confirms it happened and repeats how long the link lasts — a title
    tooltip (the previous approach) is too easy to miss and gone too fast to
    read, and a native alert() looks out of place next to the rest of the
    app. FileChip's onclick has no Livewire component of its own to
    $dispatch through, so it opens this the same way Livewire would: a plain
    "open-modal" CustomEvent on window, carrying this modal's id, which is
    exactly what Filament's own modal already listens for.
--}}
<x-filament::modal id="epesi-link-copied" alert icon="heroicon-o-link" width="sm">
    <x-slot name="heading">{{ __('Link copied to clipboard') }}</x-slot>
    <x-slot name="description">{{ __('It will work for 7 days.') }}</x-slot>
</x-filament::modal>
