{{--
    Inline styles rather than Tailwind utilities: the panel uses Filament's
    precompiled stylesheet, which has no classes the app adds.
--}}
<x-filament-widgets::widget>
    <x-filament::section :heading="__('Agenda')" icon="heroicon-o-calendar-days" compact>
        <x-slot name="afterHeader">
            <div style="display: flex; align-items: center; gap: 0.25rem;">
                {{ $this->createEventAction }}
                <x-filament::icon-button
                    icon="heroicon-o-arrows-pointing-out"
                    color="gray"
                    size="sm"
                    tag="a"
                    :href="$this->calendarUrl()"
                    :label="__('Fullscreen')"
                    :tooltip="__('Fullscreen')"
                />
                @include('filament.dashboard.configure-applet')
            </div>
        </x-slot>

        <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.875rem; max-height: 28rem; overflow-y: auto; padding-right: 0.5rem;">
            @forelse ($this->events() as $day => $rows)
                @php($date = \Illuminate\Support\Carbon::parse($day))
                <div wire:key="agenda-{{ $day }}">
                    <div style="font-weight: 600; margin-bottom: 0.25rem;{{ $date->isToday() ? ' color: var(--primary-600);' : '' }}">
                        @if ($date->isToday())
                            {{ __('Today') }}
                        @elseif ($date->isTomorrow())
                            {{ __('Tomorrow') }}
                        @else
                            {{ $date->translatedFormat('l') }}
                        @endif
                        <span style="font-weight: 400; opacity: 0.6;">{{ $date->format(\Epesi\Modules\RegionalSettings\Models\RegionalSetting::dateFormat()) }}</span>
                    </div>
                    <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.25rem;">
                        @foreach ($rows as $row)
                            <li style="display: flex; gap: 0.5rem; align-items: baseline;">
                                <span style="width: 2.75rem; flex-shrink: 0; opacity: 0.75; white-space: nowrap;">
                                    {{ $row['event']->allDay ? '' : $row['event']->start->format(\Epesi\Modules\RegionalSettings\Models\RegionalSetting::timeFormat()) }}
                                </span>
                                <a
                                    href="{{ $row['event']->url }}"
                                    style="flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                    @if ($row['tooltip'])
                                        x-tooltip="{ content: @js($row['tooltip']), theme: $store.theme, allowHTML: true }"
                                    @endif
                                >
                                    {{ $row['event']->title }}
                                </a>
                                <span style="display: flex; align-items: center; gap: 0.25rem; flex-shrink: 0; font-size: 0.75rem; opacity: 0.6;">
                                    @if ($row['icon'])
                                        <x-filament::icon :icon="$row['icon']" :size="\Filament\Support\Enums\IconSize::Small" class="epesi-agenda-type-icon" />
                                    @endif
                                    {{ $row['type'] }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p style="opacity: 0.6; margin: 0;">{{ __('Nothing on your agenda.') }}</p>
            @endforelse
        </div>
    </x-filament::section>

    {{-- The + (New event) opens its modal here: a widget has no page or table to render it. --}}
    <x-filament-actions::modals />
</x-filament-widgets::widget>
