{{-- "My records" / "All records" select, first in a list's table toolbar. See ListRecords::setMyRecordsMode().
     A list page may hand over its own `options` (value => label) and the selected `value` (Notes does). --}}
@php
    $options = $button['options'] ?? ['mine' => __('My records'), 'all' => __('All records')];
    $value = $button['value'] ?? ($button['active'] ? 'mine' : 'all');
@endphp
<div class="epesi-browse-tabs">
    <x-filament::input.wrapper>
        <x-filament::input.select wire:change="setMyRecordsMode($event.target.value)" aria-label="{{ __('Records') }}">
            @foreach ($options as $optionValue => $label)
                <option value="{{ $optionValue }}" @selected($value === $optionValue)>{{ $label }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
