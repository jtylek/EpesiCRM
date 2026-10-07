{{-- The converter itself: on the Dashboard inside the applet's section, in Exchange Rates' modal alone. --}}
@php
    $currencies = $this->currencies();
@endphp
<div
    class="cc"
    x-data="{
        amount: '100',
        typed(value) {
            value = value.replace(',', '.').replace(/[^0-9.]/g, '');
            const dot = value.indexOf('.');
            this.amount = (dot === -1 ? value : value.slice(0, dot + 1) + value.slice(dot + 1).replace(/\./g, '')).slice(0, 18);
        },
        get result() {
            if ($wire.rate === null) return null;
            const decimals = $wire.decimals;
            const factor = Math.pow(10, decimals);
            const value = Math.round((parseFloat(this.amount) || 0) * $wire.rate * factor) / factor;
            return new Intl.NumberFormat(document.documentElement.lang || undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(value);
        },
    }"
>
    {{-- Code only in the selects, so the pair and the date fit one line; the name is the tooltip. --}}
    <div class="cc-pair">
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="from" :aria-label="__('From')" :title="$currencies[$this->from] ?? ''">
                @foreach ($currencies as $code => $label)
                    <option value="{{ $code }}" title="{{ $label }}">{{ $code }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>

        <x-filament::icon-button icon="heroicon-o-arrows-right-left" color="gray" wire:click="swap" :label="__('Swap')" :tooltip="__('Swap')" />

        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="to" :aria-label="__('To')" :title="$currencies[$this->to] ?? ''">
                @foreach ($currencies as $code => $label)
                    <option value="{{ $code }}" title="{{ $label }}">{{ $code }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>

        <x-filament::input.wrapper>
            <x-filament::input type="date" wire:model.live.blur="date" :aria-label="__('Date')" :title="__('Date')" />
        </x-filament::input.wrapper>
    </div>

    <div class="cc-sides">
        <label class="cc-side cc-input">
            <span class="cc-side-label">{{ __('Amount') }}</span>
            <span class="cc-side-value">
                <input type="text" inputmode="decimal" :value="amount" x-on:input="typed($event.target.value); $event.target.value = amount" x-on:focus="$event.target.select()" />
                <span class="cc-side-code">{{ $this->from }}</span>
            </span>
        </label>

        <div class="cc-side cc-output">
            <span class="cc-side-label">{{ __('Converted') }}</span>
            <span class="cc-side-value" x-show="result !== null">
                <span x-text="result"></span>
                <span class="cc-side-code">{{ $this->to }}</span>
            </span>
            <span class="cc-none" x-show="result === null" x-cloak>
                {{ __('No rate for :from → :to on :date.', ['from' => $this->from, 'to' => $this->to, 'date' => $this->date]) }}
            </span>
        </div>
    </div>

    @if ($this->rate !== null)
        <div class="cc-rate">1 {{ $this->from }} = {{ $this->formattedRate() }} {{ $this->to }} · {{ $this->source }}</div>
    @endif
</div>
