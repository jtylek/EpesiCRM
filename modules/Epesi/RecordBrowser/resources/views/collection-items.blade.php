{{--
    A collection field on the View page (Field::collectionLines()): one line
    per item, primary first — its kind as a badge, its one-line summary, and
    the administrator's fields after it.

    @var list<array{kind: ?string, summary: string, extra: array<string, string>}> $items
--}}
<div class="epesi-collection-items">
    @foreach ($items as $item)
        <div class="epesi-collection-item">
            @if (filled($item['kind']))
                <x-filament::badge color="gray">{{ $item['kind'] }}</x-filament::badge>
            @endif
            <span>{{ $item['summary'] }}</span>
            @foreach ($item['extra'] as $label => $value)
                <span class="epesi-collection-extra">{{ $label }}: {{ $value }}</span>
            @endforeach
        </div>
    @endforeach
</div>
