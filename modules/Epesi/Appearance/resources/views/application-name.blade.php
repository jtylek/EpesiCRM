{{--
    Above the Themes table (ListThemes::content()) — the one global setting
    that isn't per-theme or per-user: what the sidebar/topbar calls this
    installation, in place of "epesi".
--}}
<form wire:submit="saveAppName" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
    <label for="appName" style="font-weight: 600;">{{ __('Application name:') }}</label>
    <x-filament::input.wrapper style="max-width: 20rem; flex: 1; min-width: 12rem;">
        <x-filament::input type="text" wire:model="appName" id="appName" maxlength="64" />
    </x-filament::input.wrapper>
    <x-filament::button type="submit" size="sm" icon="heroicon-m-check">
        {{ __('Save') }}
    </x-filament::button>
    @error('appName') <p style="color: rgb(var(--danger-600, 220 38 38)); font-size: 0.875rem; width: 100%;">{{ $message }}</p> @enderror
</form>
