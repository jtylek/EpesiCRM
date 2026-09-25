{{-- All / Favorites / Recent, at the start of a recordset list's table toolbar. See ListRecords. --}}
<x-filament::tabs class="epesi-browse-tabs">
    @foreach ($tabs as $key => $tab)
        <x-filament::tabs.item
            :active="(string) $activeTab === (string) $key"
            :icon="$tab->getIcon()"
            wire:click="$set('activeTab', {{ \Illuminate\Support\Js::from((string) $key) }})"
        >
            {{ $tab->getLabel() }}
        </x-filament::tabs.item>
    @endforeach
</x-filament::tabs>
