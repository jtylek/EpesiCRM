{{-- Active / Inactive / All select, beside "My records" in a list's table toolbar. See ListRecords::setStatusMode(). --}}
<div class="epesi-browse-tabs">
    <x-filament::input.wrapper>
        <x-filament::input.select wire:change="setStatusMode($event.target.value)" aria-label="{{ __('Status') }}">
            <option value="active" @selected($toggle['value'] === 'active')>{{ __('Active') }}</option>
            <option value="inactive" @selected($toggle['value'] === 'inactive')>{{ __('Inactive') }}</option>
            <option value="all" @selected($toggle['value'] === 'all')>{{ __('All') }}</option>
            @if ($toggle['value'] === 'other')
                <option value="other" selected>{{ __('Other') }}</option>
            @endif
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
