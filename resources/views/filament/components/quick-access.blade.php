{{--
    "Quick Access": icon-only links to the modules a user picked in User
    Settings → Quick Access, in the top bar, right before the search box
    (MainPanelProvider, App\Support\QuickAccess). The label shows as a tooltip.
    Hidden below the lg breakpoint, where the top bar has no room for it; the
    sidebar is the way around there.
--}}
@php
    $quickAccessItems = \App\Support\QuickAccess::items();
@endphp

@if ($quickAccessItems !== [])
    <style>
        .epesi-quick-access { display: none; }

        @media (min-width: 1024px) {
            .epesi-quick-access { display: flex; align-items: center; gap: 0.125rem; margin-inline-end: 0.75rem; }

            /* Each icon gets a slot as tall as the search box and wider, leaving room between glyphs, so they line up evenly. */
            .epesi-quick-access .fi-icon-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex: none;
                width: 3rem !important;
                height: 2.25rem !important;
            }

            /* The module being viewed, in the accent colour (as the sidebar does). */
            .epesi-quick-access .fi-icon-btn.is-active { color: var(--primary-600); }
            .dark .epesi-quick-access .fi-icon-btn.is-active { color: var(--primary-400); }

            .epesi-quick-access .fi-icon-btn .fi-icon { width: 1.75rem !important; height: 1.75rem !important; }
        }
    </style>

    {{--
        The active icon follows the address in the browser rather than being
        rendered by the server: the top bar's right-hand side is kept as it is
        when a click swaps the page (the main panel runs in SPA mode), so a
        server-rendered "active" would stay on the first page's icon.
    --}}
    <div
        class="epesi-quick-access"
        aria-label="{{ __('Quick Access') }}"
        x-data="{
            path: window.location.pathname.replace(/\/+$/, ''),
            isActive(href, exact) {
                const base = new URL(href, window.location.origin).pathname.replace(/\/+$/, '')

                return exact ? this.path === base : (this.path === base || this.path.startsWith(base + '/'))
            },
        }"
        x-on:livewire:navigated.window="path = window.location.pathname.replace(/\/+$/, '')"
    >
        @foreach ($quickAccessItems as $item)
            <x-filament::icon-button
                tag="a"
                :href="$item->getUrl()"
                :icon="$item->getIcon()"
                color="gray"
                :data-exact="rtrim($item->getUrl(), '/') === rtrim(\Filament\Facades\Filament::getPanel('main')->getUrl(), '/') ? 'true' : 'false'"
                x-bind:class="{ 'is-active': isActive($el.getAttribute('href'), $el.dataset.exact === 'true') }"
                icon-size="lg"
                :label="$item->getLabel()"
                :tooltip="$item->getLabel()"
            />
        @endforeach
    </div>
@endif
