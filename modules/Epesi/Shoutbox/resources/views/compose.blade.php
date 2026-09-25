{{--
    The message box, for a Livewire component using ComposesMessages. Inline
    styles for the reason given in widget.blade.php.
--}}
<form wire:submit="send" style="display: flex; flex-direction: column; gap: 0.5rem;">
    {{-- Enter sends, Shift+Enter starts a new line. --}}
    <x-filament::input.wrapper class="fi-fo-textarea">
        <textarea
            wire:model="message"
            placeholder="{{ __('Say something…') }}"
            maxlength="2000"
            rows="2"
            style="resize: vertical; min-height: 3.75rem;"
            x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $el.form.requestSubmit() }"
        ></textarea>
    </x-filament::input.wrapper>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <x-filament::input.wrapper style="flex: 1; min-width: 0;">
            <x-filament::input.select wire:model="to">
                <option value="">{{ __('To: everyone') }}</option>
                @foreach ($this->getRecipients() as $id => $name)
                    <option value="{{ $id }}">{{ __('To: :name', ['name' => $name]) }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
        {{-- Without a target the button disables itself during every request, polls included, and blinks. --}}
        <x-filament::button type="submit" icon="heroicon-m-paper-airplane" wire:target="send">{{ __('Send') }}</x-filament::button>
    </div>
    @error('message') <p class="fi-fo-field-wrp-error-message" style="color: rgb(var(--danger-600, 220 38 38)); font-size: 0.875rem;">{{ $message }}</p> @enderror
</form>
