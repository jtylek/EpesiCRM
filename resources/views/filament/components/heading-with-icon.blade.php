@php
    use Filament\Support\Enums\IconSize;

    $size ??= IconSize::ExtraLarge;
@endphp

<span style="display: inline-flex; align-items: center; gap: 0.375rem; vertical-align: middle;">
    {{ \Filament\Support\generate_icon_html($icon, size: $size) }}
    <span>{{ $heading }}</span>
</span>
