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
                <a href="{{ $this->accountsUrl() }}" style="display: inline-flex; align-items: center; gap: 0.35rem; margin-inline-start: 0.35rem; padding: 0.2rem 0.55rem; border-radius: 9999px; background: color-mix(in oklab, var(--primary-500) 12%, transparent); color: var(--primary-600); font-weight: 500; text-decoration: none; white-space: nowrap;">
                    <x-filament::icon icon="heroicon-m-link" aria-hidden="true" style="width: 0.875rem; height: 0.875rem;" />
                    {{ __('Set up a mail account') }}
                </a>
            </p>
        </x-filament::section>
    @else
        {{--
            The page's own gutter (the theme's compact-tables.css) would otherwise
            border every side of the frame; scoped to this page only, since
            it's rendered inside the Livewire-swapped page content.
        --}}
        {{--
            The account switcher (getHeaderActions()) is stretched to fill the
            header row instead of hugging its label's width.
        --}}
        <style>
            #fi-main-content{padding-inline:0!important}
            {{--
                compact-tables.css itself sets .fi-page-header-main-ctn's bottom
                padding to 10px (not 0) for exactly this page, and at higher
                specificity (html.epesi-compact :where(...) ...) than a bare
                class selector here can beat — doubling the class matches its
                specificity so the two are compared by source order (this page
                loads after that stylesheet) instead of losing outright. Left
                at 10px, that padding sits below the iframe's height (which
                fills to the viewport edge via fit()), forcing the whole page
                to scroll a few pixels.
            --}}
            html.epesi-compact .fi-page-header-main-ctn.fi-page-header-main-ctn{padding-block-end:0!important}
            .fi-header-actions-ctn{width:100%}
            .fi-header-actions-ctn>.fi-ac{width:100%}
            .fi-full-width-dropdown-trigger{width:100%;justify-content:space-between}
            {{-- The panel's own accent color, not a fixed blue (RecordBrowserServiceProvider tints its keyboard-nav row highlight the same way). --}}
            .epesi-mailbox-switching{background:color-mix(in oklab, var(--primary-500) 12%, transparent);color:var(--primary-700)}
            .dark .epesi-mailbox-switching{background:color-mix(in oklab, var(--primary-500) 20%, transparent);color:var(--primary-400)}
        </style>
        {{--
            wire:ignore keeps Livewire from re-rendering (and so reloading) the
            frame; new logins arrive as the epesi-roundcube-load event.

            switching isn't wire:loading — a wire:loading indicator tracking
            mountAction stayed stuck forever, most likely because switching
            accounts re-renders this very header (getHeaderActions() changes),
            and Livewire never fires wire:loading's own "finish" step for a
            request whose target element got replaced by that re-render.
            Alpine state isn't affected by that: load() sets it, and it clears
            once the frame it just pointed at a new URL actually finishes
            loading (the real wait — the ticket login and IMAP connect inside
            the frame, not the quick round trip that picks the account).
        --}}
        <div
            wire:ignore
            x-data="{
                lastLogin: 0,
                broken: false,
                switching: false,
                {{-- The folder to open the mail list on once logged in, and whether that has been done. --}}
                folder: null,
                folderApplied: false,
                {{--
                    Every Roundcube page has rcmail, and epesi_sso's own page
                    sets epesiRoundcube. Anything else (a Laravel 404, a page
                    without its scripts) means the web server isn't serving
                    Roundcube's URLs itself.
                --}}
                check() {
                    this.switching = false;
                    this.$nextTick(() => this.fit());

                    const frame = this.$refs.frame;

                    if (!frame.getAttribute('src')) {
                        return;
                    }

                    try {
                        this.broken = !(frame.contentWindow.rcmail || frame.contentWindow.epesiRoundcube);
                    } catch (e) {
                        this.broken = false;
                        return;
                    }

                    if (!this.broken) {
                        this.bridgeCommandPalette(frame);
                        this.bridgeFolder(frame);
                    }
                },
                {{--
                    A keydown inside the frame's own document never reaches
                    window.top, so the "/" quick switcher (command-palette.
                    blade.php) needs its own copy of the same guard here, and
                    forwards to the same open-modal event it listens for. A
                    fresh frame document (every reload) needs this again.
                --}}
                bridgeCommandPalette(frame) {
                    frame.contentWindow.addEventListener('keydown', (event) => {
                        if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey) {
                            return;
                        }

                        const target = frame.contentDocument.activeElement;

                        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(target?.tagName) || target?.isContentEditable) {
                            return;
                        }

                        if (document.querySelector('.fi-modal.fi-modal-open')) {
                            return;
                        }

                        event.preventDefault();
                        window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'command-palette' } }));
                    });
                },
                {{--
                    Roundcube switches folders without a page load, so the
                    frame's own event is the way to know: each one is passed on
                    to be kept, and the folder kept from last time is opened
                    when the mail list first shows.
                --}}
                bridgeFolder(frame) {
                    const rc = frame.contentWindow.rcmail;

                    if (!rc || rc.env.task !== 'mail' || rc.env.action) {
                        return;
                    }

                    if (!this.folderApplied && this.folder && rc.env.mailbox !== this.folder) {
                        this.folderApplied = true;
                        frame.contentWindow.location.href = @js(\Epesi\Modules\Roundcube\Roundcube::url(['_task' => 'mail'])) + '&_mbox=' + encodeURIComponent(this.folder);

                        return;
                    }

                    this.folderApplied = true;
                    rc.addEventListener('selectfolder', (event) => {
                        if (event.folder && event.folder !== this.folder) {
                            this.folder = event.folder;
                            this.$wire.rememberFolder(event.folder);
                        }
                    });
                },
                load(url, isSwitch = false, folder = null) {
                    this.folder = folder;
                    this.folderApplied = false;
                    this.switching = isSwitch;
                    this.$nextTick(() => this.fit());
                    this.colorMode(this.$store.theme);
                    this.$refs.frame.src = url;
                },
                {{-- 1px below the frame: this box's bottom border. --}}
                fit() {
                    const frame = this.$refs.frame;
                    frame.style.height = Math.max(320, Math.floor(window.innerHeight - frame.getBoundingClientRect().top - 1)) + 'px';
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
            x-init="load(@js($frameUrl), false, @js($this->rememberedFolder())); $nextTick(() => fit())"
            x-effect="colorMode($store.theme)"
            x-on:resize.window="fit()"
            x-on:message.window="receive($event)"
            x-on:epesi-roundcube-load.window="load($event.detail.url, true, $event.detail.folder ?? null)"
            style="border: 1px solid rgb(128 128 128 / 0.25); overflow: hidden;"
        >
            <div x-cloak x-show="switching" class="epesi-mailbox-switching" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                <x-filament::loading-indicator class="h-4 w-4" />
                {{ __('Switching account — please wait…') }}
            </div>
            <div x-cloak x-show="broken" style="padding: 0.75rem 1rem; font-size: 0.875rem; color: rgb(220 38 38);">
                {{ __('The mail client didn\'t load: the web server sent Roundcube\'s pages to Epesi instead of serving them itself. The web server must serve the :dir directory as plain files, including its directory URLs (?_task=mail) and its static.php/... URLs. Ask your administrator to adjust the web server configuration.', ['dir' => 'public/epesi-webmail']) }}
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
