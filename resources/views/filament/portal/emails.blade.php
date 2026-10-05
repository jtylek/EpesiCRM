@php($livewire = $getLivewire())
<ul style="display:flex;flex-direction:column;gap:.75rem">
    @foreach ($rows as $row)
        <li style="display:flex;flex-wrap:wrap;align-items:center;gap:.5rem 1rem" wire:key="email-{{ $row['id'] }}">
            <span>{{ $row['value'] }}</span>

            @if ($row['primary'])
                <x-filament::badge color="warning">{{ __('Primary') }}</x-filament::badge>
            @endif

            @if ($row['verified'])
                <x-filament::badge color="success">{{ __('Verified') }}</x-filament::badge>
            @else
                <x-filament::badge color="danger">{{ __('Not verified') }}</x-filament::badge>
            @endif

            <span style="display:flex;gap:1rem;margin-inline-start:auto">
                @if (! $row['verified'])
                    {{ ($livewire->resendEmailVerificationAction)(['id' => $row['id']]) }}
                @elseif (! $row['primary'])
                    {{ ($livewire->makeEmailPrimaryAction)(['id' => $row['id']]) }}
                @endif

                @unless ($row['primary'])
                    {{ ($livewire->removeEmailAction)(['id' => $row['id']]) }}
                @endunless
            </span>
        </li>
    @endforeach
</ul>
