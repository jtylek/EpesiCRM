{{-- "My records" / "All records" select, first in a list's table toolbar. See ListRecords::setMyRecordsMode(). --}}
<div class="epesi-browse-tabs">
    <x-filament::input.wrapper>
        <x-filament::input.select wire:change="setMyRecordsMode($event.target.value)" aria-label="{{ __('Records') }}">
            <option value="mine" @selected($button['active'])>{{ __('My records') }}</option>
            <option value="all" @selected(! $button['active'])>{{ __('All records') }}</option>
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
