{{--
    Inline styles rather than Tailwind utilities: the panel uses Filament's
    precompiled stylesheet, which has no classes a module adds.
--}}
<x-filament-widgets::widget>
    <x-filament::section :heading="__('Mail')" icon="heroicon-o-envelope" compact>
        <x-slot name="afterHeader">
            <x-filament::icon-button
                icon="heroicon-m-arrow-path"
                color="gray"
                size="sm"
                :label="__('Check now')"
                wire:click="refreshCounts"
            />
        </x-slot>

        {{-- Matches the server-side cache: asking more often gets the same answer. --}}
        <div wire:poll.180s style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.875rem;">
            <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.375rem;">
                @foreach ($this->accounts() as $account)
                    @php($unread = $this->unread($account))
                    <li wire:key="account-{{ $account->id }}" style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem;">
                        <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $account->email }}">
                            {{ $account->name }} <span style="opacity: .6;">{{ $account->email }}</span>
                        </span>
                        @if ($unread['count'] === null)
                            <x-filament::badge color="danger" :tooltip="$unread['error']">{{ __('unavailable') }}</x-filament::badge>
                        @else
                            <x-filament::badge :color="$unread['count'] > 0 ? 'warning' : 'gray'">
                                {{ __(':count unread', ['count' => $unread['count']]) }}
                            </x-filament::badge>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div>
                <div style="font-weight: 600; margin-bottom: 0.25rem;">{{ __('Recently archived') }}</div>
                <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.25rem;">
                    @forelse ($this->recent() as $mail)
                        <li wire:key="mail-{{ $mail->id }}" style="display: flex; gap: 0.5rem; align-items: baseline;">
                            <span style="opacity: .6; white-space: nowrap;">{{ $mail->date?->format('m-d H:i') }}</span>
                            <a href="{{ $this->mailUrl($mail) }}" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-decoration: underline;">
                                {{ $mail->subject ?: __('(no subject)') }}
                            </a>
                        </li>
                    @empty
                        <li style="opacity: .6;">{{ __('Nothing archived yet. Move messages to your archive folder, or send from a contact.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
