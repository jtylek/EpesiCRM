<x-filament-panels::page>
    {{--
        In the page's head (Livewire's @assets), not here in its content: on
        a click from another page (the main panel's SPA mode) a script here
        would only run after the x-init below has already looked for it.
    --}}
    @assets
        @vite('resources/js/calendar.js')
    @endassets

    <div
        wire:ignore
        x-data
        x-init="window.initEpesiCalendar($el, $wire)"
        id="epesi-calendar"
        data-locale="{{ app()->getLocale() }}"
        data-timezone="{{ \Epesi\Modules\RegionalSettings\Models\RegionalSetting::timezoneName() }}"
        data-date-format="{{ \Epesi\Modules\RegionalSettings\Models\RegionalSetting::dateFormat() }}"
        data-hour12="{{ str_contains(\Epesi\Modules\RegionalSettings\Models\RegionalSetting::timeFormat(), 'A') ? 1 : 0 }}"
    ></div>
</x-filament-panels::page>
