{{--
    Inline styles rather than Tailwind utilities: the panel uses Filament's
    precompiled stylesheet, which only contains the classes Filament itself
    uses (see the Messages applet). The result is worked out by Alpine as
    the amount is typed; $wire.rate changes when the currencies or the date do.
--}}
<x-filament-widgets::widget>
    <style>
        .cc { display: flex; flex-direction: column; gap: 0.75rem; }
        .cc-pair { display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr) minmax(0, 1.4fr); gap: 0.5rem; align-items: center; }
        .cc-sides { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 0.75rem; font-variant-numeric: tabular-nums; }
        .cc-side { border-radius: 0.75rem; padding: 0.5rem 0.875rem; min-width: 0; }
        .cc-side-label { display: block; font-size: 0.75rem; opacity: 0.6; }
        .cc-side-value { display: flex; align-items: baseline; gap: 0.375rem; font-size: 1.5rem; font-weight: 600; line-height: 1.3; }
        .cc-side-value > :first-child { flex: 1; min-width: 0; overflow-wrap: anywhere; }
        .cc-side-code { font-size: 0.875rem; font-weight: 500; opacity: 0.6; }
        /* The amount: a field to type in, white and bordered like the selects. */
        .cc-input { background: white; box-shadow: 0 0 0 1px rgb(0 0 0 / 0.1), 0 1px 2px rgb(0 0 0 / 0.05); cursor: text; }
        .cc-input:focus-within { box-shadow: 0 0 0 2px var(--primary-600); }
        .dark .cc-input { background: rgb(255 255 255 / 0.05); box-shadow: 0 0 0 1px rgb(255 255 255 / 0.1); }
        .dark .cc-input:focus-within { box-shadow: 0 0 0 2px var(--primary-500); }
        .cc-input input { all: unset; width: 100%; min-width: 0; font: inherit; }
        /* The result: a read-only display. */
        .cc-output { background: rgb(0 0 0 / 0.04); }
        .dark .cc-output { background: rgb(255 255 255 / 0.03); }
        .cc-none { font-size: 0.875rem; font-weight: 500; color: rgb(217 119 6); }
        .cc-rate { font-size: 0.75rem; opacity: 0.6; text-align: center; }
        @media (max-width: 480px) { .cc-pair { grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); } .cc-pair > :last-child { grid-column: 1 / -1; } }
    </style>

    @if ($inModal)
        @include('epesi-currencies::converter-body')
    @else
        <x-filament::section :heading="__('Currency Converter')" icon="heroicon-o-calculator" compact>
            <x-slot name="afterHeader">
                @include('filament.dashboard.configure-applet')
            </x-slot>

            @include('epesi-currencies::converter-body')
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
