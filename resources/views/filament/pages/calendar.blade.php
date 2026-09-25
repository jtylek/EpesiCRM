<x-filament-panels::page>
    @vite('resources/js/calendar.js')

    <div
        wire:ignore
        x-data
        x-init="window.initEpesiCalendar($el, $wire)"
        id="epesi-calendar"
        data-locale="{{ app()->getLocale() }}"
    ></div>
</x-filament-panels::page>
