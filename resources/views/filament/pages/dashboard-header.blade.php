{{--
    Filament's page header (x-filament-panels::header) with the tab switcher
    in the middle of the line, between the breadcrumb and the header actions,
    rather than on a row of its own above the applets. Only shown when there
    is more than one tab. Same classes as Filament's, so the app's own header
    styles (the theme's compact-tables.css) still apply.
--}}
@php
    $tabs = $this->dashboardTabs;
    $current = $this->currentTab();
@endphp

<header class="fi-header fi-header-has-breadcrumbs epesi-dashboard-header">
    <style>
        @media (min-width: 640px) {
            .epesi-dashboard-header { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; }
            .epesi-dashboard-header > .fi-header-actions-ctn { justify-self: end; }
        }
        .epesi-dashboard-header > .fi-tabs { margin-top: 0.5rem; }

        {{-- Add applet/Tabs need room for a modal and a drag target; on a phone
             screen they're better reached from a tablet or desktop instead. --}}
        @media (max-width: 767px) {
            .epesi-dashboard-header > .fi-header-actions-ctn { display: none; }
        }
    </style>

    <div>
        <x-filament::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
    </div>

    @if ($tabs->count() > 1)
        <x-filament::tabs>
            @foreach ($tabs as $tab)
                <x-filament::tabs.item
                    :active="$tab->is($current)"
                    wire:click="$set('tab', {{ $tab->id }})"
                    wire:key="dashboard-tab-{{ $tab->id }}"
                >
                    {{ $tab->name }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    @else
        <div></div>
    @endif

    <div class="fi-header-actions-ctn">
        <x-filament::actions :actions="$this->getCachedHeaderActions()" />
    </div>
</header>
