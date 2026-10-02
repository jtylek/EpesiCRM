@assets
    <script>{!! file_get_contents(base_path('modules/Epesi/RecordBrowser/resources/js/click-to-fill.js')) !!}</script>
@endassets

<div x-data="epesiClickToFill()" x-on:click.window="fill($event)" x-on:epesi-click-to-fill-toggle.window="open = !open">
    <div x-show="open" x-cloak class="mt-3 space-y-3">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('Paste text, scan it, select words in the desired order, then click a text field to replace its contents.') }}
        </p>
        <template x-if="open && !scanned">
            <label class="block">
                <span class="text-sm">{{ __('Text to scan') }}</span>
                <x-filament::input.wrapper class="fi-fo-textarea">
                    <textarea x-model="source" rows="4" aria-label="{{ __('Text to scan') }}"></textarea>
                </x-filament::input.wrapper>
            </label>
        </template>
        <div x-show="scanned" class="flex flex-wrap gap-2">
            <template x-for="(word, index) in words" :key="index">
                <x-filament::button type="button" color="primary" size="sm"
                    x-bind:class="{ 'fi-color': selected.includes(index) }"
                    x-on:click="select(index)" x-bind:aria-pressed="selected.includes(index)">
                    <span x-text="word"></span>
                    <strong x-show="selected.includes(index)" x-text="selected.indexOf(index) + 1"></strong>
                </x-filament::button>
            </template>
        </div>
        <x-filament::button type="button" color="gray" x-show="!scanned" x-on:click="scan()">
            {{ __('Scan text') }}
        </x-filament::button>
        <x-filament::button type="button" color="gray" x-show="scanned" x-on:click="scanned = false; selected = []">
            {{ __('Edit source text') }}
        </x-filament::button>
    </div>
</div>
