{{--
    A collection field on the View page (Field::collectionLines()): one line
    per item, primary first — its kind as a badge, its one-line summary (with
    a link icon, opening in a new tab, when it links somewhere, as a web
    address does), the item's own badges (a phone number's messengers, each
    opening its app) and the administrator's fields after it.

    @var list<array{kind: ?string, summary: string, url: ?string, links: list<array{label: string, url: ?string}>, extra: array<string, string>}> $items
--}}
<div class="epesi-collection-items">
    @foreach ($items as $item)
        <div class="epesi-collection-item">
            @if (filled($item['kind']))
                <x-filament::badge color="gray">{{ $item['kind'] }}</x-filament::badge>
            @endif
            @if (filled($item['url']))
                <x-filament::badge tag="a" :href="$item['url']" target="_blank" rel="noopener" icon="heroicon-o-link" icon-position="after">{{ $item['summary'] }}</x-filament::badge>
            @else
                <span>{{ $item['summary'] }}</span>
            @endif
            @foreach ($item['links'] as $link)
                @if (filled($link['url']))
                    {{-- An app's own scheme (viber://) opens the app, not a tab. --}}
                    <x-filament::badge tag="a" :href="$link['url']" :target="str_starts_with($link['url'], 'http') ? '_blank' : null" :spa-mode="false" rel="noopener" color="success" icon="heroicon-o-chat-bubble-left-right">{{ $link['label'] }}</x-filament::badge>
                @else
                    <x-filament::badge color="gray" icon="heroicon-o-chat-bubble-left-right">{{ $link['label'] }}</x-filament::badge>
                @endif
            @endforeach
            @foreach ($item['extra'] as $label => $value)
                <span class="epesi-collection-extra">{{ $label }}: {{ $value }}</span>
            @endforeach
        </div>
    @endforeach
</div>
