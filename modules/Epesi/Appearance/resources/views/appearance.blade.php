<x-filament-panels::page>
    @if ($this->hasThemes())
        <form wire:submit="save">
            {{ $this->form }}

            <x-filament::button type="submit" icon="heroicon-o-check" class="mt-6">
                {{ __('Save') }}
            </x-filament::button>
        </form>
    @else
        <x-filament::section>
            <p>{{ __('Your administrator hasn\'t set up any themes yet.') }}</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
