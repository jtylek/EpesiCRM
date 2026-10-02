{{--
    The user menu's "Keyboard help" item pops this open (its alpineClickHandler
    in MainPanelProvider) rather than linking to a page, the same way the
    command palette and file preview modal are wired: a static reference of
    every shortcut in AI-shared/Keyboard-shortcuts.md, kept in sync by hand
    since there's no single registry of them to build it from automatically.
    Each key is a real <x-filament::badge> rather than a hand-rolled <kbd>, so
    it's themed (light/dark, colour) by Filament's own CSS instead of Tailwind
    utility classes this file would have to get exactly right itself.
--}}
@php
    $groups = [
        [
            'heading' => __('Anywhere'),
            'shortcuts' => [
                ['keys' => ['/'], 'does' => __('Opens the quick switcher — jump to any page by name')],
                ['keys' => ['Backspace'], 'does' => __('Goes back a page')],
            ],
        ],
        [
            'heading' => __('List page'),
            'shortcuts' => [
                ['keys' => ['A', 'F', 'R'], 'does' => __('Switches to the All / Favorites / Recent tab')],
                ['keys' => ['M'], 'does' => __('Switches between My records and All records')],
                ['keys' => ['I'], 'does' => __('Cycles through Active, Inactive and All records')],
                ['keys' => ['S'], 'does' => __('Focuses the search field')],
                ['keys' => ['N'], 'does' => __('Opens the "New …" page')],
                ['keys' => ['↑', '↓'], 'does' => __('Moves the highlighted row')],
                ['keys' => ['PageUp', 'PageDown'], 'does' => __("Turns the table's page")],
                ['keys' => ['Enter'], 'does' => __('Opens the highlighted row, or runs the search')],
                ['keys' => ['Space'], 'does' => __('Previews the highlighted row in place, if it has one')],
            ],
        ],
        [
            'heading' => __('Create or Edit page'),
            'shortcuts' => [
                ['keys' => ['Ctrl', '/', 'Cmd', '+', 'S'], 'does' => __('Saves')],
                ['keys' => ['Ctrl', '/', 'Cmd', '+', 'E'], 'does' => __('Cancels — discards the changes and leaves')],
            ],
        ],
    ];
@endphp

<x-filament::modal id="keyboard-help" icon="heroicon-o-command-line" width="2xl">
    <x-slot name="heading">{{ __('Keyboard help') }}</x-slot>

    <div class="space-y-5">
        @foreach ($groups as $group)
            <div>
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $group['heading'] }}
                </h3>

                <div class="mt-2 grid grid-cols-[auto_1fr] items-center gap-x-4 gap-y-2.5">
                    @foreach ($group['shortcuts'] as $shortcut)
                        <div class="flex flex-wrap items-center gap-1">
                            @foreach ($shortcut['keys'] as $key)
                                @if (in_array($key, ['/', '+'], true))
                                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ $key }}</span>
                                @else
                                    <x-filament::badge color="gray" size="xs">
                                        {{ $key }}
                                    </x-filament::badge>
                                @endif
                            @endforeach
                        </div>

                        <p class="text-sm text-gray-500 sm:whitespace-nowrap dark:text-gray-400">
                            {{ $shortcut['does'] }}
                        </p>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-filament::modal>
