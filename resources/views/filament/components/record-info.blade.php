@php
    $rows = [[__('Record ID'), $id, null], ...$rows];
@endphp

<div class="epesi-record-info">
    @foreach ($rows as [$label, $who, $when])
        <div class="epesi-record-info-label">{{ $label }}</div>
        @if ($loop->first)
            <div class="epesi-record-info-value epesi-record-info-wide">{{ $who }}</div>
        @else
            <div class="epesi-record-info-value epesi-record-info-who">{{ $who }}</div>
            <div class="epesi-record-info-value">{{ $when }}</div>
        @endif
    @endforeach
</div>
