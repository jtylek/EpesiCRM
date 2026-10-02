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
        data-calendar-system="{{ \Epesi\Modules\RegionalSettings\Models\RegionalSetting::calendarSystem() }}"
        data-hijri-variant="{{ \Epesi\Modules\RegionalSettings\Models\RegionalSetting::hijriVariant() }}"
        data-hour12="{{ str_contains(\Epesi\Modules\RegionalSettings\Models\RegionalSetting::timeFormat(), 'A') ? 1 : 0 }}"
        data-work-start="{{ \App\Support\Calendar\WorkingHours::get()['start'] }}"
        data-work-end="{{ \App\Support\Calendar\WorkingHours::get()['end'] }}"
        data-list-range="{{ \App\Support\Calendar\ListRange::get() }}"
        data-expanded="{{ json_encode(\App\Support\Calendar\WorkingHours::expanded()) }}"
        data-labels="{{ json_encode([
            'before' => __('Before :time'),
            'after' => __('After :time'),
            'expand' => __('Expand'),
            'collapse' => __('Collapse'),
            'sevenDays' => __('7 days'),
            'ummalquraRange' => __('Umm al-Qura dates are supported for Hijri years 1300 through 1600.'),
            'one' => __(':count event'),
            'other' => __(':count events'),
        ]) }}"
    ></div>
</x-filament-panels::page>
