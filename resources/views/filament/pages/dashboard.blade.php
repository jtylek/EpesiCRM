{{--
    Each column is a Livewire wire:sort list, and all three share one group,
    so an applet can be dragged within its column or across to another. The
    handle is the applet's own title bar (a section header, or a table
    widget's header), as in Epesi; the rest of the applet stays clickable and
    its text selectable. wire:sort:group stays the last wire:sort attribute:
    Livewire stops reading an element's wire:sort attributes once it gets to
    that one.

    An applet's key carries a hash of its settings, so saving them mounts it
    afresh with the new ones. epesi-applet-high-priority tints a task or
    call's row, as legacy's applet_info_format() did.

    Inline styles rather than Tailwind utilities: the panel uses Filament's
    precompiled stylesheet, which has no classes the app adds. The drop
    targets show while an applet is dragged: the dragged element carries
    `sortable-ghost`, and only an applet's is a column's own child.
    Livewire's `sorting` on <body> won't do, since it is there for any
    wire:sort drag, a row dragged inside an applet (the Priority list's)
    included. The applets' shadow is set through Filament's --tw-shadow rather
    than box-shadow, which also carries the card's 1px ring. In dark mode it is
    a faint light glow: a dark shadow can't show on the near-black page.

    An applet table fits its column without a scrollbar: a wrapping column
    (a task's title) may break a long word such as a domain name, which
    otherwise sets the column's narrowest width, and the first column starts
    1rem in, under the applet's title, rather than Filament's 1.5rem. Columns
    sit 1rem apart rather than 1.5rem, and the last one ends .5rem from the
    card's edge rather than 1.5rem, so the wrapping column gets the room. A
    row actions cell keeps its own padding (the theme's compact-tables.css). An applet
    that stacks its columns (Tasks) has records, not rows: .fi-ta-record, with
    the same side padding.
--}}
@php
    // Also the cursor: move rule below.
    $handle = '.fi-wi-widget > .fi-section > .fi-section-header, .fi-wi-table .fi-ta-header';
    $columns = $this->getAppletColumns();
@endphp

<x-filament-panels::page>
    <style>
        .epesi-dashboard { display: grid; gap: 1.5rem; grid-template-columns: minmax(0, 1fr); }
        @media (max-width: 767px) { .epesi-dashboard:not(:has(> .epesi-dashboard-column > .sortable-ghost)) > .epesi-dashboard-column:not(:has(> *)) { display: none; } }
        @media (min-width: 768px) { .epesi-dashboard { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1280px) { .epesi-dashboard { grid-template-columns: repeat(var(--epesi-dashboard-columns), minmax(0, 1fr)); } }
        /* min-width: 0 overrides a grid item's default min-width: auto, which
           otherwise refuses to shrink below its widest child's natural width
           (an applet's title, say) and stretches the column past the phone's
           screen — the whole page then scrolls sideways to reach a row's
           actions instead of the title wrapping. */
        .epesi-dashboard-column { display: flex; flex-direction: column; gap: 1.5rem; border-radius: 0.75rem; min-width: 0; }
        .epesi-dashboard:has(> .epesi-dashboard-column > .sortable-ghost) > .epesi-dashboard-column { min-height: 6rem; outline: 2px dashed color-mix(in srgb, var(--primary-500) 50%, transparent); outline-offset: 0.25rem; }
        .epesi-dashboard :is(.fi-wi-widget > .fi-section > .fi-section-header, .fi-wi-table .fi-ta-header) { cursor: move; }
        .epesi-dashboard-column > .sortable-ghost { opacity: 0.4; }
        .epesi-dashboard :is(.fi-wi-widget > .fi-section, .fi-wi-table .fi-ta-ctn) { --tw-shadow: 0 1px 3px rgb(0 0 0 / 0.08), 0 4px 12px rgb(0 0 0 / 0.1); }
        .dark .epesi-dashboard :is(.fi-wi-widget > .fi-section, .fi-wi-table .fi-ta-ctn) { --tw-shadow: 0 0 2px rgb(255 255 255 / 0.1), 0 0 12px rgb(255 255 255 / 0.1); }
        .epesi-dashboard :is(.fi-ta-row, .fi-ta-record).epesi-applet-high-priority { background-color: color-mix(in srgb, var(--danger-500) 8%, transparent); }
        .epesi-dashboard .fi-wi-table .fi-ta-record-content { padding-inline: 0.5rem; }
        .epesi-dashboard .fi-wi-table .fi-ta-record-content-ctn { padding-block: 0.375rem; }
        .epesi-dashboard .fi-wi-table .fi-ta-text.fi-wrapped { overflow-wrap: anywhere; }
        .epesi-dashboard .fi-wi-table :is(.fi-ta-text:not(.fi-inline), .fi-ta-header-cell) { padding-inline: 0.5rem; }
        .epesi-dashboard .fi-wi-table .fi-ta-header-cell:first-of-type { padding-inline-start: 1rem; }
        .epesi-dashboard .fi-wi-table .fi-ta-cell:first-child { padding-inline-start: 0.5rem; }
        .epesi-dashboard .fi-wi-table .fi-ta-cell:last-child { padding-inline-end: 0; }

        /* The Tabs form: the reorder handle, the name and the delete button on one line. */
        .epesi-tabs-repeater .fi-fo-repeater-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; }
        .epesi-tabs-repeater .fi-fo-repeater-item-header { display: contents; }
        .epesi-tabs-repeater .fi-fo-repeater-item-header-start-actions { order: 0; }
        .epesi-tabs-repeater .fi-fo-repeater-item-content { order: 1; flex: 1; min-width: 0; padding: 0; }
        .epesi-tabs-repeater .fi-fo-repeater-item-header-end-actions { order: 2; }
    </style>

    @if (array_merge(...$columns) === [])
        <x-filament::empty-state
            icon="heroicon-o-squares-plus"
            :heading="__('No applets on this tab')"
            :description="__('Add one with the Add applet button.')"
        />
    @endif

    @php($fullWidthApplets = collect(array_merge(...$columns))->filter(fn ($applet): bool => defined($applet->widget.'::APPLET_FULL_WIDTH')))

    {{-- An applet with APPLET_FULL_WIDTH (the Notes) is the tab's one widget: no columns, no dragging. --}}
    @if ($fullWidthApplets->isNotEmpty())
        @foreach ($fullWidthApplets as $applet)
            <div wire:key="applet-{{ $applet->id }}">
                @livewire($applet->widget, $this->getAppletProperties($applet), key('applet-'.$applet->id.'-'.md5(json_encode($applet->settings))))
            </div>
        @endforeach
    @else
    <div class="epesi-dashboard" style="--epesi-dashboard-columns: {{ $this::COLUMNS }}">
        @foreach ($columns as $column => $applets)
            <div
                wire:sort.ghost="moveApplet"
                wire:sort:config="{ handle: @js($handle) }"
                wire:sort:group-id="{{ $column }}"
                wire:sort:group="dashboard-applets"
                class="epesi-dashboard-column"
                {{-- An applet with APPLET_FULL_WIDTH (the Notes) takes the whole row; the other columns drop below it. --}}
                @if (collect($applets)->contains(fn ($applet): bool => defined($applet->widget.'::APPLET_FULL_WIDTH')))
                    style="grid-column: 1 / -1;"
                @endif
            >
                @foreach ($applets as $applet)
                    <div wire:key="applet-{{ $applet->id }}" wire:sort:item="{{ $applet->id }}">
                        @livewire($applet->widget, $this->getAppletProperties($applet), key('applet-'.$applet->id.'-'.md5(json_encode($applet->settings))))
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
    @endif
</x-filament-panels::page>
