{{-- A list page's own select in the table toolbar (ListRecords::getToolbarSelects()): `options` (value => label),
     the selected `value`, the Livewire `method` that takes the chosen value, and `dates` (from/until inputs
     bound to the filter state) shown beside it while true. --}}
<div class="epesi-browse-tabs">
    <x-filament::input.wrapper>
        <x-filament::input.select wire:change="{{ $select['method'] }}($event.target.value)" aria-label="{{ $select['label'] }}">
            @foreach ($select['options'] as $optionValue => $label)
                <option value="{{ $optionValue }}" @selected((string) $select['value'] === (string) $optionValue)>{{ $label }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
@if (! empty($select['dates']))
    <x-filament::input.wrapper>
        <x-filament::input type="date" wire:model.live="{{ $select['dates']['from'] }}" aria-label="{{ __('From') }}" />
    </x-filament::input.wrapper>
    <x-filament::input.wrapper>
        <x-filament::input type="date" wire:model.live="{{ $select['dates']['until'] }}" aria-label="{{ __('Until') }}" />
    </x-filament::input.wrapper>
@endif
