{{-- A section applet's gear: IsApplet::configureAppletAction() for a header that isn't a table's. --}}
@if ($this->appletId !== null)
    <x-filament::icon-button
        icon="heroicon-o-cog-6-tooth"
        color="gray"
        size="sm"
        :label="__('Configure')"
        :tooltip="__('Configure')"
        wire:click="$dispatch('configure-applet', { applet: {{ $this->appletId }} })"
    />
@endif
