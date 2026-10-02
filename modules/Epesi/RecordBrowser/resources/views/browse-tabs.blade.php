{{-- All / Favorites / Recent, as a select at the start of a recordset list's table toolbar. See ListRecords. --}}
<div class="epesi-browse-tabs">
    <x-filament::input.wrapper :prefix-icon="$tabs[(string) $activeTab]?->getIcon() ?? collect($tabs)->first()?->getIcon()">
        <x-filament::input.select wire:model.live="activeTab" aria-label="{{ __('Show') }}">
            @foreach ($tabs as $key => $tab)
                <option value="{{ $key }}" @selected((string) $activeTab === (string) $key)>{{ $tab->getLabel() }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
