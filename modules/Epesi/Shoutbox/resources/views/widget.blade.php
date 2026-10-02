{{--
    Inline styles rather than Tailwind utilities: the panel uses Filament's
    precompiled stylesheet (no custom theme build), which only contains the
    classes Filament itself uses, so a module's own utility classes would
    never be generated.
--}}
<x-filament-widgets::widget>
    <x-filament::section :heading="__('Messages')" icon="heroicon-o-chat-bubble-left-right" compact>
        <x-slot name="afterHeader">
            <div style="display: flex; align-items: center; gap: 0.25rem;">
                <x-filament::icon-button
                    icon="heroicon-o-arrows-pointing-out"
                    color="gray"
                    size="sm"
                    tag="a"
                    :href="\Epesi\Modules\Shoutbox\Filament\Pages\Shoutbox::getUrl()"
                    :label="__('Fullscreen')"
                    :tooltip="__('Fullscreen')"
                />
                @include('filament.dashboard.configure-applet')
            </div>
        </x-slot>

        <div wire:poll.10s="poll" style="display: flex; flex-direction: column; gap: 0.75rem;">
            @include('epesi-shoutbox::compose')

            <ul style="display: flex; flex-direction: column; gap: 0.5rem; max-height: max(24rem, calc(100vh - 17rem)); overflow-y: auto; font-size: 0.875rem; margin: 0; padding: 0; list-style: none;">
                @forelse ($this->getShouts() as $shout)
                    @php($background = $shout->bubbleBackground(auth()->user()))
                    <li
                        wire:key="shout-{{ $shout->id }}"
                        style="border-radius: 0.5rem; padding: 0.5rem 0.75rem; margin-left: {{ $shout->user_id === auth()->id() ? '1.5rem' : '0' }}; margin-right: {{ $shout->user_id === auth()->id() ? '0' : '1.5rem' }}; background: {{ $background }};"
                    >
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; font-size: 0.75rem; opacity: 0.75;">
                            <span>
                                <button type="button" style="font-weight: 600;" title="{{ __('Reply privately') }}" wire:click="replyTo({{ $shout->user_id }})">
                                    {{ $shout->author?->displayName() ?? __('Anonymous') }}
                                </button>
                                @if ($shout->recipient)
                                    → <span style="font-weight: 600;">{{ $shout->recipient->displayName() }}</span>
                                @endif
                                · <span title="{{ \Epesi\Modules\RegionalSettings\Models\RegionalSetting::display($shout->created_at) }}">{{ $shout->created_at?->diffForHumans() }}</span>
                            </span>
                            @if ($shout->canBeDeletedBy(auth()->user()))
                                <x-filament::icon-button
                                    icon="heroicon-m-trash"
                                    color="gray"
                                    size="xs"
                                    :label="__('Delete')"
                                    wire:click="delete({{ $shout->id }})"
                                    wire:confirm="{{ __('Delete this message?') }}"
                                />
                            @endif
                        </div>
                        <p style="white-space: pre-line; overflow-wrap: anywhere; margin: 0;">{{ $shout->message }}</p>
                    </li>
                @empty
                    <li style="opacity: 0.6;">{{ __('No messages yet.') }}</li>
                @endforelse
            </ul>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
