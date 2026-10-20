{{--
    A record change made on a View page (the status popup that also writes a
    note, a header action, an addon's own action) dispatches `addon-changed`
    (RecordBrowserServiceProvider). The relation managers (Notes, Mail...)
    are Livewire components of their own, which a re-render of the page
    leaves alone, so they reload here. The page itself already re-renders.

    Back/Forward: wire:navigate restores a cached copy of the page, which is
    stale after a change made meanwhile. A popstate (browser or panel Back
    button) is flagged, and the page that appears next reloads all its
    Livewire components. Clicking a link fetches the page fresh, so it is
    left alone.

    A lazy component still showing its placeholder (an addon tab whose lazy
    load hasn't come back yet) is skipped: it loads fresh anyway, and a
    $refresh sent before its lazy load makes Livewire skip hydration, so a
    relation manager renders without its table ("$table must not be
    accessed before initialization").
--}}
<div
    x-data="{
        loaded(component) {
            return ! (component.isLazy && ! component.hasBeenLazyLoaded)
        },
    }"
    x-init="
        if (! window.epesiPopstateFlagged) {
            window.epesiPopstateFlagged = true;
            window.addEventListener('popstate', () => { window.epesiPopped = true }, true);
        }

        if (window.epesiPopped) {
            window.epesiPopped = false;
            $nextTick(() => Livewire.all().filter(loaded).forEach((component) => component.$wire.$refresh()));
        }
    "
    x-on:addon-changed.window="
        Livewire.all()
            .filter((component) => /relation-manager/i.test(component.name) && loaded(component))
            .forEach((component) => component.$wire.$refresh())
    "
    hidden
></div>
