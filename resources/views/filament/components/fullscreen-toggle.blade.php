{{--
    Full screen from the top bar, as F11 does, left of the notifications
    bell (MainPanelProvider). A browser leaves full screen whenever a new
    page loads, so the main panel runs in SPA mode to keep it across clicks.
    Hidden where the browser can't put a page in full screen (iPhone Safari).
    Its own label rather than the applets' "Fullscreen", which opens a page.
--}}
<div
    x-data="{ supported: document.fullscreenEnabled, on: !! document.fullscreenElement }"
    x-on:fullscreenchange.document="on = !! document.fullscreenElement"
    x-show="supported"
    x-cloak
    style="display: flex;"
>
    <x-filament::icon-button
        color="gray"
        icon="heroicon-o-arrows-pointing-out"
        icon-size="lg"
        :label="__('Full screen')"
        :tooltip="__('Full screen')"
        x-show="! on"
        x-on:click="document.documentElement.requestFullscreen()"
    />

    <x-filament::icon-button
        color="gray"
        icon="heroicon-o-arrows-pointing-in"
        icon-size="lg"
        :label="__('Exit full screen')"
        :tooltip="__('Exit full screen')"
        x-cloak
        x-show="on"
        x-on:click="document.exitFullscreen()"
    />
</div>
