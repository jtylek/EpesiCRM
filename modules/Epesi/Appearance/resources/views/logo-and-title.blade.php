{{--
    Administration → Appearance → Logo & Title (Pages\LogoAndTitle): the title of
    the main application; and, for the login pages and for the customer portal
    separately, a title and a logo for light mode and one for dark mode.
    Defaults: "epesi", "epesi", "Customer Portal", and the built-in epesi logo.
--}}
@php
    use Epesi\Modules\Appearance\Models\AppearanceSetting;

    $livewire = $getLivewire();

    $errorStyle = 'color: rgb(var(--danger-600, 220 38 38)); font-size: 0.875rem;';

    $fields = [
        'appName' => __('Main application title'),
        'loginTitle' => __('Login title'),
        'portalTitle' => __('Customer portal title'),
    ];

    $areas = [
        __('Login pages') => ['title' => 'loginTitle', 'target' => 'login'],
        __('Customer portal') => ['title' => 'portalTitle', 'target' => 'portal'],
    ];

    $modes = [
        '' => [__('Light mode'), '#ffffff', '#d4d4d8'],
        '-dark' => [__('Dark mode'), '#111111', '#3f3f46'],
    ];
@endphp

<form wire:submit="save" style="display: flex; flex-direction: column; gap: 1rem;">
    <x-filament::section :heading="__('Main application')" compact>
        <div style="max-width: 20rem;">
            <label for="appName" style="display: block; font-weight: 600; margin-bottom: 0.25rem;">{{ $fields['appName'] }}:</label>
            <x-filament::input.wrapper>
                <x-filament::input type="text" wire:model="appName" id="appName" maxlength="64" />
            </x-filament::input.wrapper>
            @error('appName') <p style="{{ $errorStyle }}">{{ $message }}</p> @enderror
        </div>
    </x-filament::section>

    @foreach ($areas as $heading => $area)
        <x-filament::section :heading="$heading" compact>
            <div style="max-width: 20rem; margin-bottom: 1rem;">
                <label for="{{ $area['title'] }}" style="display: block; font-weight: 600; margin-bottom: 0.25rem;">{{ $fields[$area['title']] }}:</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="{{ $area['title'] }}" id="{{ $area['title'] }}" maxlength="64" />
                </x-filament::input.wrapper>
                @error($area['title']) <p style="{{ $errorStyle }}">{{ $message }}</p> @enderror
            </div>

            <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                @foreach ($modes as $suffix => [$modeLabel, $background, $border])
                    @php
                        $kind = $area['target'].$suffix;
                        $current = AppearanceSetting::logoUrl($kind);
                        $picked = $livewire->logos[$kind] ?? null;
                    @endphp
                    <div style="flex: 1; min-width: 14rem; max-width: 22rem;">
                        <div style="font-weight: 600; margin-bottom: 0.25rem;">{{ __('Logo') }} — {{ $modeLabel }}</div>
                        <div style="background: {{ $background }}; border: 1px solid {{ $border }}; border-radius: 0.5rem; min-height: 5rem; display: flex; align-items: center; justify-content: center; padding: 0.75rem; margin-bottom: 0.5rem;">
                            @if ($picked)
                                @if ($picked->isPreviewable())
                                    <img src="{{ $picked->temporaryUrl() }}" alt="" style="max-height: 3.5rem; max-width: 100%;">
                                @else
                                    <span style="font-size: 0.875rem; color: #888;">{{ $picked->getClientOriginalName() }}</span>
                                @endif
                            @elseif ($current)
                                <img src="{{ $current }}" alt="" style="max-height: 3.5rem; max-width: 100%;">
                            @else
                                @if ($suffix === '-dark' && AppearanceSetting::logoUrl($area['target']))
                                    <span style="font-size: 0.875rem; color: #888;">{{ __('Same as light mode') }}</span>
                                @else
                                    <img src="{{ asset($suffix === '' ? 'images/logo-light.png' : 'images/logo-dark.png') }}" alt="epesi" style="max-height: 3.5rem; max-width: 100%;">
                                @endif
                            @endif
                        </div>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                            <input type="file" id="logo-{{ $kind }}" wire:model="logos.{{ $kind }}" accept=".png,.jpg,.jpeg,.webp,.svg,image/*" style="display: none;">
                            <x-filament::button tag="label" for="logo-{{ $kind }}" size="sm" color="gray" icon="heroicon-m-arrow-up-tray" style="cursor: pointer;">
                                {{ __('Choose file') }}
                            </x-filament::button>
                            @if ($current && ! $picked)
                                <x-filament::button type="button" size="sm" color="gray" wire:click="removeLogo('{{ $kind }}')">
                                    {{ __('Remove') }}
                                </x-filament::button>
                            @endif
                        </div>
                        @error('logos.'.$kind) <p style="{{ $errorStyle }}">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endforeach

    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <x-filament::button type="submit" icon="heroicon-m-check">
            {{ __('Save') }}
        </x-filament::button>
        <x-filament::button
            type="button"
            color="gray"
            icon="heroicon-m-arrow-uturn-left"
            wire:click="resetToDefaults"
            wire:confirm="{{ __('Reset the titles and logos to the epesi defaults? Uploaded logos are deleted.') }}"
        >
            {{ __('Reset to default') }}
        </x-filament::button>
    </div>
</form>

<div x-data="{
        src: '',
        title: '',
        // Fit the popup to the login card with 24px of page around it: the frame starts wide,
        // the popup takes the card's width, then the frame is cut to the card's height.
        fit(frame) {
            const panel = frame.parentElement.parentElement;
            const clip = frame.parentElement;
            const card = () => frame.contentDocument.querySelector('.fi-simple-main');
            if (! card()) return;
            panel.style.width = '40rem';
            frame.style.height = '0px';
            frame.style.marginTop = '0px';
            panel.style.width = (card().getBoundingClientRect().width + 48) + 'px';
            requestAnimationFrame(() => {
                const r = card().getBoundingClientRect();
                frame.style.height = Math.ceil(r.bottom + 24) + 'px';
                frame.style.marginTop = '-' + Math.max(0, Math.floor(r.top - 24)) + 'px';
                clip.style.height = Math.ceil(r.bottom - r.top + 48) + 'px';
            });
        },
    }" x-on:keydown.escape.window="src = ''">
<x-filament::section :heading="__('Preview')" compact style="margin-top: 1rem;">
    <p style="font-size: 0.875rem; margin-bottom: 0.75rem; opacity: 0.8;">
        {{ __('Shows the login page as it is saved, in light and in dark mode.') }}
    </p>
    <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
        @foreach ($livewire->previewUrls() as $label => $urls)
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-weight: 600;">{{ $label }}:</span>
                @foreach (['light' => __('Light'), 'dark' => __('Dark')] as $mode => $modeLabel)
                    <x-filament::button type="button" size="sm" color="gray" x-on:click="src = '{{ $urls[$mode] }}'; title = '{{ addslashes($label.' — '.$modeLabel) }}'">
                        {{ $modeLabel }}
                    </x-filament::button>
                @endforeach
            </div>
        @endforeach
    </div>
</x-filament::section>

{{-- The login card in a window of its own, just big enough for the card (the page's version label below it is cut off). --}}
<template x-teleport="body">
    <div x-show="src" x-cloak x-on:click.self="src = ''"
        style="position: fixed; inset: 0; z-index: 100; background: rgba(0, 0, 0, 0.6); padding: 1rem; overflow: auto; display: flex;">
        <div style="display: flex; flex-direction: column; width: 34rem; max-width: 100%; margin: auto; background: #18181b; border-radius: 0.5rem; overflow: hidden; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 1rem; color: #fff;">
                <span style="font-weight: 600;" x-text="title"></span>
                <x-filament::icon-button icon="heroicon-m-x-mark" color="gray" :label="__('Close preview')" x-on:click="src = ''" />
            </div>
            {{-- Cut to the card: the page's margin above it and the version label below it are left out. --}}
            <div style="overflow: hidden; height: 28rem;">
                <iframe x-bind:src="src" scrolling="no" title="{{ __('Preview') }}" x-on:load="fit($el)"
                    style="display: block; width: 100%; height: 40rem; border: 0; background: #fff;"></iframe>
            </div>
        </div>
    </div>
</template>
</div>
