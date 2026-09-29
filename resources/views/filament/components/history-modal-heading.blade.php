{{--
    The History action's modal heading (ViewRecord::recordInfoAndHistoryKebab()).
    A Filament action modal has no header slot to add a button to beside the
    heading (only `modalHeading()`, rendered inside the `<h2>`), unlike the
    fully custom file-preview-modal.blade.php — so the maximize toggle rides
    along in the heading Htmlable instead and is pulled out to sit next to
    the close button with plain CSS (resources/css/filament/epesi/history-
    modal.css), the same way FileChip's own preview offers it.
--}}
<span class="epesi-history-heading">
    {{ __('Record History') }}
</span>

<span x-data="{ maximized: false }">
    <x-filament::icon-button
        color="gray"
        icon="heroicon-o-arrows-pointing-out"
        :label="__('Maximize')"
        :tooltip="__('Maximize')"
        class="epesi-history-maximize-btn"
        x-show="! maximized"
        x-on:click.stop="maximized = true; $el.closest('.fi-modal-window').classList.add('epesi-history-max')"
    />

    <x-filament::icon-button
        color="gray"
        icon="heroicon-o-arrows-pointing-in"
        :label="__('Restore size')"
        :tooltip="__('Restore size')"
        class="epesi-history-maximize-btn"
        x-cloak
        x-show="maximized"
        x-on:click.stop="maximized = false; $el.closest('.fi-modal-window').classList.remove('epesi-history-max')"
    />
</span>
