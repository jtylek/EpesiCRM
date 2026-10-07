<div class="fi-ta-user-filter">
    <x-filament::input.wrapper>
        <x-filament::input.select wire:model.live="auditUser" aria-label="{{ __('User Name') }}">
            <option value="">{{ __('All users') }}</option>
            @foreach ($users as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
