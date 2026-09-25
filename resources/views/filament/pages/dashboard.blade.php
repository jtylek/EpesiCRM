{{--
    Each column is a Livewire wire:sort list, and all three share one group,
    so an applet can be dragged within its column or across to another. The
    handle is the applet's own title bar (a section header, or a table
    widget's header), as in Epesi; the rest of the applet stays clickable and
    its text selectable. wire:sort:group stays the last wire:sort attribute:
    Livewire stops reading an element's wire:sort attributes once it gets to
    that one.

    Inline styles rather than Tailwind utilities: the panel uses Filament's
    precompiled stylesheet, which has no classes the app adds. Livewire puts
    `sorting` on <body> for the length of a drag.
--}}
@php
    // Also the cursor: move rule below.
    $handle = '.fi-wi-widget > .fi-section > .fi-section-header, .fi-wi-table .fi-ta-header';
@endphp

<x-filament-panels::page>
    <style>
        .epesi-dashboard { display: grid; gap: 1.5rem; }
        @media (max-width: 767px) { body:not(.sorting) .epesi-dashboard-column:not(:has(> *)) { display: none; } }
        @media (min-width: 768px) { .epesi-dashboard { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1280px) { .epesi-dashboard { grid-template-columns: repeat(var(--epesi-dashboard-columns), minmax(0, 1fr)); } }
        .epesi-dashboard-column { display: flex; flex-direction: column; gap: 1.5rem; border-radius: 0.75rem; }
        body.sorting .epesi-dashboard-column { min-height: 6rem; outline: 2px dashed color-mix(in srgb, var(--primary-500) 50%, transparent); outline-offset: 0.25rem; }
        .epesi-dashboard :is(.fi-wi-widget > .fi-section > .fi-section-header, .fi-wi-table .fi-ta-header) { cursor: move; }
        .epesi-dashboard .sortable-ghost { opacity: 0.4; }
    </style>

    <div class="epesi-dashboard" style="--epesi-dashboard-columns: {{ $this::COLUMNS }}">
        @foreach ($this->getAppletColumns() as $column => $applets)
            <div
                wire:sort.ghost="moveApplet"
                wire:sort:config="{ handle: @js($handle) }"
                wire:sort:group-id="{{ $column }}"
                wire:sort:group="dashboard-applets"
                class="epesi-dashboard-column"
            >
                @foreach ($applets as $class => $widget)
                    <div wire:key="applet-{{ $class }}" wire:sort:item="{{ $class }}">
                        @livewire($class, $this->getAppletProperties($widget), key($class))
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
