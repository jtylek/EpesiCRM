{{--
    Every row can be dragged by its grip at any time (Livewire's wire:sort);
    the rest of the row stays clickable. Classes in a <style> block rather
    than Tailwind utilities: the panel uses Filament's precompiled stylesheet,
    which has no classes a module adds.
--}}
@use('Epesi\Modules\PriorityList\PriorityList')

@php
    $entries = $this->getPriorityEntries();
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('Priority list (:count/:limit)', ['count' => $entries->count(), 'limit' => PriorityList::LIMIT])"
        icon="heroicon-o-flag"
        compact
    >
        <x-slot name="afterHeader">
            @include('filament.dashboard.configure-applet')
        </x-slot>

        <style>
            .epesi-pl { list-style: none; margin: 0; padding: 0; font-size: 0.875rem; }
            .epesi-pl-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0; }
            .epesi-pl-item + .epesi-pl-item { border-top: 1px solid color-mix(in srgb, var(--gray-500) 20%, transparent); }
            .epesi-pl-handle { display: flex; cursor: grab; opacity: 0.5; touch-action: none; }
            .epesi-pl-handle:hover { opacity: 1; }
            .epesi-pl-place { min-width: 1.25rem; text-align: right; opacity: 0.6; font-variant-numeric: tabular-nums; }
            .epesi-pl-body { flex: 1; min-width: 0; }
            .epesi-pl-title { font-weight: 500; overflow-wrap: anywhere; }
            a.epesi-pl-title:hover { text-decoration: underline; }
            .epesi-pl-meta { font-size: 0.75rem; opacity: 0.7; }
            .epesi-pl-overdue { color: var(--danger-600); }
            .dark .epesi-pl-overdue { color: var(--danger-400); }
            .epesi-pl .sortable-ghost { opacity: 0.4; }
            .epesi-pl-empty { padding: 1.5rem 0.5rem; text-align: center; }
        </style>

        @if ($entries->isEmpty())
            <div class="epesi-pl-empty">
                <p style="font-weight: 600; margin: 0;">{{ __('Your priority list is empty') }}</p>
                <p style="font-size: 0.875rem; opacity: 0.7; margin: 0.25rem 0 0;">
                    {{ __('Put up to :limit tasks, meetings or phone calls on it with Add to priority list on their page.', ['limit' => PriorityList::LIMIT]) }}
                </p>
            </div>
        @else
            <ol class="epesi-pl" wire:sort.ghost="reorderPriority">
                @foreach ($entries as $entry)
                    @php
                        $record = $entry->record;
                        $url = PriorityList::url($record);
                        $due = PriorityList::formatDue($record);
                        $details = PriorityList::details($record);
                    @endphp
                    <li class="epesi-pl-item" wire:key="priority-{{ $entry->id }}" wire:sort:item="{{ $entry->id }}">
                        <span class="epesi-pl-handle" wire:sort:handle title="{{ __('Drag to reorder') }}">
                            <x-filament::icon icon="heroicon-m-bars-2" :size="\Filament\Support\Enums\IconSize::Small" />
                        </span>
                        <span class="epesi-pl-place">{{ $loop->iteration }}</span>
                        <div
                            class="epesi-pl-body"
                            @if ($details)
                                x-tooltip="{ content: @js($details), theme: $store.theme, allowHTML: true }"
                            @endif
                        >
                            @if ($url)
                                <a class="epesi-pl-title" href="{{ $url }}">{{ PriorityList::title($record) }}</a>
                            @else
                                <span class="epesi-pl-title">{{ PriorityList::title($record) }}</span>
                            @endif
                            <div class="epesi-pl-meta">
                                {{ PriorityList::describe($record) }}
                                @if ($due)
                                    · <span @class(['epesi-pl-overdue' => PriorityList::isOverdue($record)])>{{ $due }}</span>
                                @endif
                            </div>
                        </div>
                        @if (PriorityList::canComplete(auth()->user(), $record))
                            <x-filament::icon-button
                                icon="heroicon-m-check"
                                color="success"
                                size="sm"
                                :label="__('Done: close it')"
                                :tooltip="__('Done: close it')"
                                wire:click="completePriority({{ $entry->id }})"
                                wire:confirm="{{ __('Close :record? Its status becomes Closed, and it leaves every priority list it is on.', ['record' => PriorityList::label($record)]) }}"
                            />
                        @endif
                        <x-filament::icon-button
                            icon="heroicon-m-x-mark"
                            color="gray"
                            size="sm"
                            :label="__('Take off the list')"
                            :tooltip="__('Take off the list')"
                            wire:click="removePriority({{ $entry->id }})"
                        />
                    </li>
                @endforeach
            </ol>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
