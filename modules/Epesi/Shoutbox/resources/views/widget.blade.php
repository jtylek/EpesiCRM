{{--
    Inline styles rather than Tailwind utilities: the panel uses Filament's
    precompiled stylesheet (no custom theme build), which only contains the
    classes Filament itself uses, so a module's own utility classes would
    never be generated.
--}}
<x-filament-widgets::widget>
    <x-filament::section :heading="__('Shoutbox')" icon="heroicon-o-chat-bubble-left-right" compact>
        <div wire:poll.10s="poll" style="display: flex; flex-direction: column; gap: 0.75rem;">
            @include('epesi-shoutbox::compose')

            <ul style="display: flex; flex-direction: column; gap: 0.5rem; max-height: 24rem; overflow-y: auto; font-size: 0.875rem; margin: 0; padding: 0; list-style: none;">
                @forelse ($this->getShouts() as $shout)
                    <li
                        wire:key="shout-{{ $shout->id }}"
                        style="border-radius: 0.5rem; padding: 0.5rem 0.75rem; background: {{ $shout->to_user_id !== null ? 'color-mix(in srgb, var(--primary-500) 12%, transparent)' : 'color-mix(in srgb, var(--gray-500) 8%, transparent)' }};"
                    >
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; font-size: 0.75rem; opacity: 0.75;">
                            <span>
                                <button type="button" style="font-weight: 600;" title="{{ __('Reply privately') }}" wire:click="replyTo({{ $shout->user_id }})">
                                    {{ $shout->author?->displayName() ?? __('Anonymous') }}
                                </button>
                                @if ($shout->recipient)
                                    → {{ $shout->recipient->displayName() }}
                                @endif
                                · <span title="{{ $shout->created_at }}">{{ $shout->created_at?->diffForHumans() }}</span>
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
