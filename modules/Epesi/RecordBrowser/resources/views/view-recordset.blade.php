<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <x-filament::button type="submit" icon="heroicon-o-check" class="mt-6">
            {{ __('Save') }}
        </x-filament::button>
    </form>
</x-filament-panels::page>
