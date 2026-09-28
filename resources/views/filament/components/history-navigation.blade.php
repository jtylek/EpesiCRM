{{--
    Back / forward from the top bar, after the search box and before the
    notifications bell (MainPanelProvider). Real full screen
    (fullscreen-toggle.blade.php, right after these) hides the browser's own
    chrome, taking its back/forward buttons with it, so the panel needs its
    own that keep working there too. Backspace is the keyboard shortcut for
    the same Back button — guarded like every other single-key shortcut
    here (command-palette.blade.php): never while typing (Backspace's usual
    job there) or while a modal is open.
--}}
<div
    x-data="{}"
    x-on:keydown.window="
        if (event.key !== 'Backspace') return;
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName) || event.target.isContentEditable) return;
        if (document.querySelector('.fi-modal.fi-modal-open')) return;

        event.preventDefault();
        window.history.back();
    "
    style="display: flex;"
>
    <x-filament::icon-button
        color="gray"
        icon="heroicon-o-arrow-left"
        icon-size="lg"
        :label="__('Back')"
        :tooltip="__('Back')"
        x-on:click="window.history.back()"
    />

    <x-filament::icon-button
        color="gray"
        icon="heroicon-o-arrow-right"
        icon-size="lg"
        :label="__('Forward')"
        :tooltip="__('Forward')"
        x-on:click="window.history.forward()"
    />
</div>
