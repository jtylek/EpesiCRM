{{--
    The demo's cookie bar (App\Filament\Support\DemoAnalytics): shown until the
    visitor accepts or declines, and again from "Cookie settings". Its own
    styles, because the panels use Filament's precompiled stylesheet.
--}}
<style>
    .epesi-cookie-settings{padding:.5rem 1rem;text-align:center;font-size:.75rem;color:var(--gray-500)}
    .epesi-cookie-settings button{text-decoration:underline;cursor:pointer}
    .epesi-cookie-bar{position:fixed;left:1rem;right:1rem;bottom:1rem;z-index:50;max-width:48rem;margin:0 auto;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem 1.5rem;padding:1rem 1.25rem;border:1px solid var(--gray-200);border-radius:.75rem;background:#fff;box-shadow:0 10px 25px -5px rgb(0 0 0 / .15);font-size:.875rem}
    .dark .epesi-cookie-bar{border-color:var(--gray-700);background:var(--gray-900)}
    .epesi-cookie-bar > p{flex:1 1 20rem}
    .epesi-cookie-bar a{text-decoration:underline}
    .epesi-cookie-bar > div{display:flex;gap:.5rem}
</style>

<div x-data="{ open: epesiAnalytics.choice() === null }">
    <div class="epesi-cookie-settings">
        <button type="button" x-on:click="open = true">{{ __('Cookie settings') }}</button>
    </div>

    <div class="epesi-cookie-bar" role="region" aria-label="{{ __('Cookie consent') }}" x-show="open" x-cloak>
        <p>
            {{ __('We\'d like to use Google Analytics cookies to learn how visitors use this demo. No cookies are set and no visits are tracked unless you accept.') }}
            <a href="https://policies.google.com/technologies/partner-sites" target="_blank" rel="noopener">{{ __('How Google uses this data') }}</a>
        </p>

        <div>
            <x-filament::button color="gray" size="sm" x-on:click="epesiAnalytics.decline(); open = false">
                {{ __('Decline') }}
            </x-filament::button>
            <x-filament::button size="sm" x-on:click="epesiAnalytics.accept(); open = false">
                {{ __('Accept') }}
            </x-filament::button>
        </div>
    </div>
</div>
