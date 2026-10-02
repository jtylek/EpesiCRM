{{--
    Inline styles rather than Tailwind utilities: the panel uses Filament's
    precompiled stylesheet, which has no classes a module adds.
--}}
<x-filament-widgets::widget>
    <x-filament::section :heading="__('Mail')" icon="heroicon-o-envelope" compact>
        <x-slot name="afterHeader">
            <div style="display: flex; align-items: center; gap: 0.25rem;">
                <x-filament::icon-button
                    icon="heroicon-m-arrow-path"
                    color="gray"
                    size="sm"
                    :label="__('Check now')"
                    wire:click="refreshCounts"
                />
                @if ($fullscreenUrl = $this->defaultMailboxUrl())
                    <x-filament::icon-button
                        icon="heroicon-o-arrows-pointing-out"
                        color="gray"
                        size="sm"
                        tag="a"
                        :href="$fullscreenUrl"
                        :label="__('Fullscreen')"
                        :tooltip="__('Fullscreen')"
                    />
                @endif
                @include('filament.dashboard.configure-applet')
            </div>
        </x-slot>

        {{-- Matches the server-side cache: asking more often gets the same answer. --}}
        <div wire:poll.180s>
            <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.375rem;">
                @foreach ($this->accounts() as $account)
                    @php($unread = $this->unread($account))
                    @php($mailboxUrl = $this->mailboxUrl($account))
                    @php($badgeColor = $unread['count'] === null ? 'danger' : ($unread['count'] > 0 ? 'warning' : 'gray'))
                    <li wire:key="account-{{ $account->id }}">
                        <div style="display: flex; align-items: center; gap: 0.375rem;">
                            <x-filament::badge
                                :tag="$mailboxUrl ? 'a' : 'span'"
                                :href="$mailboxUrl"
                                :color="$badgeColor"
                                style="flex: 1; min-width: 0; justify-content: flex-start; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-decoration: none;"
                            >
                                {{ $account->name }}
                            </x-filament::badge>
                            @if ($unread['count'] === null)
                                <x-filament::badge color="danger" :tooltip="$unread['error']">{{ __('unavailable') }}</x-filament::badge>
                            @else
                                <x-filament::badge :color="$badgeColor">
                                    {{ __(':count unread', ['count' => $unread['count']]) }}
                                </x-filament::badge>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
