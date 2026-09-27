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
    ></div>
</x-filament-panels::page>
