{{--
    "/" from anywhere opens a centered quick switcher: type a few letters of
    a sidebar item's name, arrow through the matches, Enter jumps there.
    The list stays empty until the first letter is typed, so it never has
    to fit every sidebar item on the screen at once.
    Guarded against firing while already typing into a field, the way a
    single-key global shortcut always has to be. The item list is exactly
    the sidebar's own (AlphabeticalNavigationManager), so nothing here
    tracks navigation separately — a new resource or page needs nothing.
--}}
@php
    // Icons are not sent here: the sidebar already renders each item's icon,
    // so the palette copies it from there when it opens.
    $paletteItems = collect(\Filament\Facades\Filament::getNavigation())
        ->flatMap(fn ($group) => $group->getItems())
        ->filter(fn ($item) => filled($item->getUrl()))
        ->map(fn ($item) => [
            'label' => $item->getLabel(),
            'url' => $item->getUrl(),
        ])
        ->values();
@endphp

<div
    x-data="{}"
    x-on:keydown.window="
        if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey) return;
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName) || event.target.isContentEditable) return;
        if (document.querySelector('.fi-modal.fi-modal-open')) return;

        event.preventDefault();
        $dispatch('open-modal', { id: 'command-palette' });
    "
>
    <x-filament::modal
        id="command-palette"
        width="lg"
        :close-button="false"
    >
        <div
            x-data="{
                query: '',
                activeIndex: 0,
                items: @js($paletteItems),
                get filtered() {
                    const query = this.query.trim().toLowerCase();

                    return query === ''
                        ? []
                        : this.items.filter((item) => item.label.toLowerCase().includes(query));
                },
                itemHtml(item) {
                    const icon = [...document.querySelectorAll('.fi-sidebar a[href]')]
                        .find((link) => link.href === item.url)
                        ?.querySelector('svg.fi-icon');
                    const label = document.createElement('span');
                    label.className = 'fi-dropdown-list-item-label';
                    label.textContent = item.label;

                    return (icon ? icon.outerHTML : '') + label.outerHTML;
                },
                go(item) {
                    if (! item) return;

                    $dispatch('close-modal', { id: 'command-palette' });
                    Alpine.navigate(item.url);
                },
            }"
            x-on:open-modal.window="if ($event.detail.id === 'command-palette') { query = ''; activeIndex = 0; $nextTick(() => $refs.commandPaletteInput.focus()); }"
        >
            <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass" inline-prefix>
                <input
                    x-ref="commandPaletteInput"
                    x-model="query"
                    x-on:input="activeIndex = 0"
                    x-on:keydown.down.prevent="activeIndex = Math.min(activeIndex + 1, filtered.length - 1)"
                    x-on:keydown.up.prevent="activeIndex = Math.max(activeIndex - 1, 0)"
                    x-on:keydown.enter.prevent="go(filtered[activeIndex])"
                    type="search"
                    autocomplete="off"
                    placeholder="{{ __('Jump to…') }}"
                    class="fi-input fi-input-has-inline-prefix"
                />
            </x-filament::input.wrapper>

            <x-filament::dropdown.list class="mt-2" x-show="filtered.length > 0">
                <template x-for="(item, index) in filtered" :key="item.url">
                    <a
                        :href="item.url"
                        :class="{ 'fi-selected': index === activeIndex }"
                        x-on:click.prevent="go(item)"
                        x-on:mouseenter="activeIndex = index"
                        x-html="itemHtml(item)"
                        class="fi-dropdown-list-item"
                    ></a>
                </template>
            </x-filament::dropdown.list>

            <p
                x-show="query.trim() !== '' && filtered.length === 0"
                x-cloak
                class="fi-global-search-no-results-message"
            >
                {{ __('No matches') }}
            </p>
        </div>
    </x-filament::modal>
</div>
