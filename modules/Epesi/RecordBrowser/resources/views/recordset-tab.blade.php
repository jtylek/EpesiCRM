<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        @if ($this->hasSave())
            <x-filament::button type="submit" icon="heroicon-o-check" class="mt-6">
                {{ __('Save') }}
            </x-filament::button>
        @endif
    </form>
</x-filament-panels::page>
