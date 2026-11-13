<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="applyFilters" class="epesi-compact-labels space-y-4">
            {{ $this->form }}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <x-filament::button type="submit" icon="heroicon-o-funnel" wire:loading.attr="disabled">
                    {{ __('Apply filters') }}
                </x-filament::button>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->filters['from'] }} — {{ $this->filters['until'] }} · {{ \Filament\Support\Facades\FilamentTimezone::get() }}
                </p>
                <x-filament::button tag="a" :href="$this->detailsUrl()" icon="heroicon-o-list-bullet">
                    {{ __('Activity details') }}
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    @php
        $summary = $this->summary;
        $stats = [
            [__('Logins'), number_format($summary['logins']), 'heroicon-o-arrow-right-on-rectangle', 'info'],
            [__('Recorded session time'), \Epesi\Modules\ActivityReport\ActivitySummary::duration($summary['seconds']), 'heroicon-o-clock', 'warning'],
            [__('Activities'), number_format($summary['activities']), 'heroicon-o-bolt', 'success'],
            [__('Days with activity'), number_format($summary['active_days']), 'heroicon-o-calendar-days', 'primary'],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as [$label, $value, $icon, $color])
            <x-filament::section>
                <div class="flex items-center justify-between gap-3">
                    <x-filament::badge :color="$color" :icon="$icon" size="lg">{{ $label }}</x-filament::badge>
                    <p class="shrink-0 text-right text-xl font-semibold whitespace-nowrap tabular-nums">{{ $value }}</p>
                </div>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section>
        <x-slot name="heading">{{ __('Activity by module') }}</x-slot>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @forelse ($summary['modules'] as $alias => $module)
                <div wire:key="module-{{ $alias }}" class="rounded-lg border border-primary-200 bg-primary-50 p-4 dark:border-primary-800 dark:bg-primary-950">
                    <div class="flex items-center justify-between gap-3">
                        <a {{ \Filament\Support\generate_href_html($this->detailsUrl($alias)) }} class="flex min-w-0 items-center gap-3 text-primary-700 hover:underline dark:text-primary-300">
                            {{ \Filament\Support\generate_icon_html($module['icon'], attributes: new \Illuminate\View\ComponentAttributeBag(['class' => 'h-6 w-6 shrink-0'])) }}
                            <span class="font-semibold">{{ $module['label'] }}</span>
                        </a>
                        <a {{ \Filament\Support\generate_href_html($this->detailsUrl($alias)) }} class="shrink-0 text-3xl leading-none font-semibold tabular-nums hover:underline" aria-label="{{ __(':module activities', ['module' => $module['label']]) }}" title="{{ __(':module activities', ['module' => $module['label']]) }}">{{ number_format($module['total']) }}</a>
                    </div>
                    <div class="mt-3 flex flex-wrap justify-end gap-2">
                        <a {{ \Filament\Support\generate_href_html($this->detailsUrl($alias, 'new')) }} aria-label="{{ __('Created :module', ['module' => $module['label']]) }}"><x-filament::badge color="success">{{ __('Created') }}: {{ number_format($module['created']) }}</x-filament::badge></a>
                        <a {{ \Filament\Support\generate_href_html($this->detailsUrl($alias, 'edit')) }} aria-label="{{ __('Updated :module', ['module' => $module['label']]) }}"><x-filament::badge color="info">{{ __('Updated') }}: {{ number_format($module['updated']) }}</x-filament::badge></a>
                        @if ($module['deleted'] > 0)
                            <a {{ \Filament\Support\generate_href_html($this->detailsUrl($alias, 'delete_restore')) }} aria-label="{{ __('Deleted :module', ['module' => $module['label']]) }}"><x-filament::badge color="danger">{{ __('Deleted') }}: {{ number_format($module['deleted']) }}</x-filament::badge></a>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400 sm:col-span-2 xl:col-span-4">{{ __('No recorded activity in this period.') }}</p>
            @endforelse
        </div>
    </x-filament::section>

    @php
        $calendar = \Epesi\Modules\ActivityReport\ActivitySummary::calendar($summary['days']);
        $levels = [
            'bg-gray-100 dark:bg-white/5',
            'bg-primary-200 dark:bg-primary-900',
            'bg-primary-400 dark:bg-primary-700',
            'bg-primary-600 dark:bg-primary-500',
            'bg-primary-800 dark:bg-primary-300',
        ];
        $dayData = fn (array $day): array => [
            'date' => $day['date'],
            'label' => \Carbon\CarbonImmutable::parse($day['date'])->translatedFormat('l, j F Y'),
            'logins' => number_format($day['logins']),
            'time' => \Epesi\Modules\ActivityReport\ActivitySummary::duration($day['seconds']),
            'activities' => number_format($day['activities']),
            'url' => $this->detailsUrl(date: $day['date']),
        ];
        $allDays = array_merge(...array_map(fn (array $week): array => array_values(array_filter($week)), $calendar['weeks'] ?: [[]]));
        $initial = collect($allDays)->last(fn (array $day): bool => $day['activities'] > 0) ?? end($allDays) ?: null;
        $weekdays = [0 => __('Mon'), 2 => __('Wed'), 4 => __('Fri')];
    @endphp

    <div
        wire:key="calendar-{{ $this->filters['from'] }}-{{ $this->filters['until'] }}-{{ $this->filters['user'] }}"
        x-data="{ selected: @js($initial ? $dayData($initial) : null) }"
    >
    <x-filament::section>
        <x-slot name="heading">{{ __('Daily activity') }}</x-slot>
        <x-slot name="description">
            <span class="flex w-full flex-wrap items-center gap-x-4 gap-y-2">
                <span>{{ trans_choice(':count activity in this period|:count activities in this period', $summary['activities'], ['count' => number_format($summary['activities'])]) }}</span>
                <span x-show="selected" x-cloak class="ms-auto flex flex-wrap items-center justify-end gap-2">
                    <span class="font-semibold text-gray-950 dark:text-white" x-text="selected?.label"></span>
                    <x-filament::badge color="info" icon="heroicon-o-arrow-right-on-rectangle">{{ __('Logins') }}: <span x-text="selected?.logins"></span></x-filament::badge>
                    <x-filament::badge color="warning" icon="heroicon-o-clock">{{ __('Recorded session time') }}: <span x-text="selected?.time"></span></x-filament::badge>
                    <x-filament::badge color="success" icon="heroicon-o-bolt">{{ __('Activities') }}: <span x-text="selected?.activities"></span></x-filament::badge>
                </span>
            </span>
        </x-slot>

        <div class="space-y-3">
            <div class="overflow-x-auto pb-1">
                <div class="grid w-max gap-[3px] text-xs text-gray-500 dark:text-gray-400"
                     style="grid-template-columns: 2rem repeat({{ count($calendar['weeks']) }}, minmax(0.75rem, 1.5rem));">
                    @foreach ($calendar['months'] as $month)
                        <div class="truncate" style="grid-row: 1; grid-column: {{ $month['column'] + 2 }} / span {{ $month['span'] }};">{{ $month['label'] }}</div>
                    @endforeach
                    @foreach ($weekdays as $row => $weekday)
                        <div class="flex items-center leading-none" style="grid-row: {{ $row + 2 }}; grid-column: 1;">{{ $weekday }}</div>
                    @endforeach
                    @foreach ($calendar['weeks'] as $column => $week)
                        @foreach ($week as $row => $day)
                            @continue($day === null)
                            @php($data = $dayData($day))
                            <a {{ \Filament\Support\generate_href_html($data['url']) }}
                               wire:key="day-{{ $day['date'] }}"
                               style="grid-row: {{ $row + 2 }}; grid-column: {{ $column + 2 }};"
                               class="aspect-square rounded-[3px] outline-offset-1 hover:outline-2 hover:outline-gray-400 {{ $levels[$day['level']] }}"
                               :class="selected?.date === @js($day['date']) && 'outline-2 outline-gray-900 dark:outline-white'"
                               title="{{ $data['label'] }}: {{ trans_choice(':count activity|:count activities', $day['activities'], ['count' => $data['activities']]) }}, {{ trans_choice(':count login|:count logins', $day['logins'], ['count' => $data['logins']]) }}, {{ $data['time'] }}"
                               aria-label="{{ $data['label'] }}: {{ __('Activity details') }}"
                               x-on:mouseenter="selected = @js($data)"
                               x-on:focus="selected = @js($data)"></a>
                        @endforeach
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end">
                <div class="flex items-center gap-[3px] text-xs text-gray-500 dark:text-gray-400">
                    <span class="me-1">{{ __('Less') }}</span>
                    @foreach ($levels as $class)
                        <span class="size-3 rounded-[3px] {{ $class }}"></span>
                    @endforeach
                    <span class="ms-1">{{ __('More') }}</span>
                </div>
            </div>
        </div>
    </x-filament::section>
    </div>
</x-filament-panels::page>
