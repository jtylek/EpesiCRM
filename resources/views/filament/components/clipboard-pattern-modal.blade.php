{{--
    The View page's "Copy to clipboard" action (ViewRecord::clipboardAction()):
    the pattern rendered against this record, in a selectable box, and a Copy
    button. navigator.clipboard needs HTTPS or localhost, so the box stays
    selectable on click for a plain-HTTP install where that API doesn't exist.

    The Copy button never puts $text into an attribute or a literal JS
    string — only into this textarea's own content, which Blade escapes as
    it would anywhere else — and reads it back from the DOM at click time
    (Legacy's clipboard button built its onclick from the raw value, which
    broke the moment it held a quote).
--}}
<div x-data="{ copied: false }" class="space-y-3">
    <textarea
        readonly
        rows="{{ max(3, substr_count($text, "\n") + 1) }}"
        onclick="this.select()"
        onfocus="this.select()"
        class="fi-input block w-full resize-y rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
    >{{ $text }}</textarea>

    <x-filament::button
        type="button"
        color="gray"
        icon="heroicon-o-clipboard-document"
        x-on:click="
            navigator.clipboard.writeText($el.closest('[x-data]').querySelector('textarea').value).then(() => {
                copied = true;
                setTimeout(() => (copied = false), 2000);
            })
        "
    >
        <span x-show="! copied">{{ __('Copy') }}</span>
        <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
    </x-filament::button>
</div>
