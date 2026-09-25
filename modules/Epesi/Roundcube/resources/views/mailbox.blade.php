{{--
    Inline styles rather than Tailwind utilities: the panel uses Filament's
    precompiled stylesheet, which has no classes a module adds.
--}}
<x-filament-panels::page>
    @if (! $this->isInstalled())
        <x-filament::section>
            <p style="font-size: 0.875rem;">
                {{ __('The mail client, Roundcube, isn\'t installed yet.') }}
            </p>
            @if ($this->installRoundcubeAction->isVisible())
                <div style="margin-top: 1rem;">{{ $this->installRoundcubeAction }}</div>
            @else
                <p style="font-size: 0.875rem; margin-top: 0.5rem;">
                    {{ __('Ask your administrator to install it.') }}
                </p>
            @endif
        </x-filament::section>
    @elseif (! $accountId)
        <x-filament::section>
            <p style="font-size: 0.875rem;">
                {{ __('You have no mail account with an incoming (IMAP) server yet.') }}
                <x-filament::link :href="$this->accountsUrl()">{{ __('Set up a mail account') }}</x-filament::link>
            </p>
        </x-filament::section>
    @else
        {{--
            wire:ignore keeps Livewire from re-rendering (and so reloading) the
            frame; new logins arrive as the epesi-roundcube-load event.
        --}}
        <div
            wire:ignore
            x-data="{
                lastLogin: 0,
                broken: false,
                {{--
                    Every Roundcube page has rcmail, and epesi_sso's own page
                    sets epesiRoundcube. Anything else (a Laravel 404, a page
                    without its scripts) means the web server isn't serving
                    Roundcube's URLs itself.
                --}}
                check() {
                    const frame = this.$refs.frame;

                    if (!frame.getAttribute('src')) {
                        return;
                    }

                    try {
                        this.broken = !(frame.contentWindow.rcmail || frame.contentWindow.epesiRoundcube);
                    } catch (e) {
                        this.broken = false;
                    }
                },
                load(url) {
                    this.colorMode(this.$store.theme);
                    this.$refs.frame.src = url;
                },
                {{-- 11px below the frame: this box's 1px border and the page's 10px bottom padding. --}}
                fit() {
                    const frame = this.$refs.frame;
                    frame.style.height = Math.max(320, Math.floor(window.innerHeight - frame.getBoundingClientRect().top - 11)) + 'px';
                },
                {{-- Roundcube's Elastic skin reads this cookie; the open page is switched in place. --}}
                colorMode(theme) {
                    document.cookie = 'colorMode=' + theme + '; path=' + @js(\Epesi\Modules\Roundcube\Roundcube::cookiePath()) + '; SameSite=Lax';

                    try {
                        this.$refs.frame.contentDocument?.documentElement.classList.toggle('dark-mode', theme === 'dark');
                    } catch (e) {}
                },
                receive(event) {
                    if (event.origin !== window.location.origin || event.source !== this.$refs.frame.contentWindow) {
                        return;
                    }

                    const type = event.data?.epesiRoundcube;

                    if (type === 'archived') {
                        this.$wire.archived();
                    }

                    {{-- At most one new login per 10 s, so a failing one can't loop. --}}
                    if (type === 'login-required' && Date.now() - this.lastLogin > 10000) {
                        this.lastLogin = Date.now();
                        this.$wire.relogin();
                    }
                },
            }"
            x-init="load(@js($frameUrl)); $nextTick(() => fit())"
            x-effect="colorMode($store.theme)"
            x-on:resize.window="fit()"
            x-on:message.window="receive($event)"
            x-on:epesi-roundcube-load.window="load($event.detail.url)"
            style="border: 1px solid rgb(128 128 128 / 0.25); border-radius: 0.75rem; overflow: hidden;"
        >
            <div x-cloak x-show="broken" style="padding: 0.75rem 1rem; font-size: 0.875rem; color: rgb(220 38 38);">
                {!! __('The mail client didn\'t load: the web server sent Roundcube\'s pages to Epesi instead of serving them itself. See "Web server requirements" in :doc.', ['doc' => '<code>AI-shared/Epesi-Laravel-Roundcube.md</code>']) !!}
            </div>
            <iframe
                x-ref="frame"
                x-on:load="check()"
                title="{{ __('Mailbox') }}"
                style="display: block; width: 100%; height: 70vh; border: 0;"
            ></iframe>
        </div>
    @endif
</x-filament-panels::page>
