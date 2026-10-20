{{-- The Linked to field (LinkedToField): a badge per linked record, its × taking it off the
     form's state at once (saved with the form), and the button opening the picker popup. --}}
@php
    $statePath = $getStatePath();
    $items = $getLinkedItems();
    $linkAction = $getAction('linkRecord');
    $disabled = $isDisabled();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }} }"
        class="epesi-linked-to"
        style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.375rem; min-height: 2.25rem;"
    >
        @foreach ($items as $token => $item)
            <span
                wire:key="{{ $getId() }}.{{ $token }}"
                x-show="(state ?? []).includes(@js($token))"
            >
                <x-filament::badge color="gray" :icon="$item['icon']" :tooltip="$item['type']">
                    {{ $item['title'] }}

                    @unless ($disabled)
                        <x-slot
                            name="deleteButton"
                            :label="__('Remove')"
                            x-on:click="state = (state ?? []).filter((token) => token !== @js($token))"
                        ></x-slot>
                    @endunless
                </x-filament::badge>
            </span>
        @endforeach

        @if ($linkAction?->isVisible())
            {{ $linkAction }}
        @endif
    </div>
</x-dynamic-component>
