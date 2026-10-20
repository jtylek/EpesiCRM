{{--
    Back / forward from the top bar, after the search box and before the
    notifications bell (MainPanelProvider). Real full screen
    (fullscreen-toggle.blade.php, right after these) hides the browser's own
    chrome, taking its back/forward buttons with it, so the panel needs its
    own that keep working there too. There is no Backspace shortcut: the
    browser's own Alt+Left / Alt+Right already do it.

    Used by every panel (the Back / Forward buttons only where $buttons is
    left on, the main panel). Going back from any page with a form — Create,
    Edit, settings, in any module — whose form has been changed asks
    first ("Do you want to leave the screen?"), so a stray Alt+Left
    or browser Back doesn't throw the changes away (onNavigate() below). "Changed" = the Livewire form state (`data`) now
    differs from what it was when the page finished loading.
--}}
<div
    x-data="{
        confirming: false,
        choice: 'stay',
        baseline: null,

        formState() {
            if (! document.querySelector('main form[wire\\:submit]')) return null;

            // The page's own form component: the one whose state is `data`
            // (Filament's convention for every Create, Edit and settings page).
            const component = window.Livewire?.all().find((c) => c.ephemeral && c.ephemeral.data !== undefined);

            if (! component) return null;

            // Text typed into a field that hasn't lost focus yet isn't in the
            // Livewire state (wire:model.blur), so the visible values count too.
            const fields = [...document.querySelectorAll('main form input:not([type=hidden]):not([type=search]), main form textarea, main form select')]
                .map((el) => (el.type === 'checkbox' || el.type === 'radio' ? el.checked : el.value));

            return JSON.stringify([component.ephemeral.data, fields]);
        },

        index: null,
        restoring: false,
        delta: 0,

        navIndex() {
            return window.navigation ? window.navigation.currentEntry.index : null;
        },

        remember() {
            this.baseline = null;
            this.index = this.navIndex();
            setTimeout(() => (this.baseline = this.formState()), 800);
        },

        // Browser Back / Alt+Left / mouse back can't be cancelled: the URL has
        // already changed. But before Livewire swaps the page (wiping the form)
        // it fires a cancelable alpine:navigate with history: true — cancelled
        // here and the dialog asked. Stay walks the history back to where it
        // was, Leave lets Livewire navigate to the URL we landed on. No history
        // entries are added, so nothing here can feed itself.
        onNavigate(event) {
            if (! event.detail?.history) return;

            if (this.restoring) {
                this.restoring = false;
                event.preventDefault();

                return;
            }

            if (! this.isDirty()) {
                this.index = this.navIndex();

                return;
            }

            event.preventDefault();
            window.epesiPopped = false;
            this.delta = this.index !== null && this.navIndex() !== null ? this.navIndex() - this.index : -1;
            this.choice = 'stay';
            this.confirming = true;
        },

        stay() {
            this.confirming = false;

            if (this.delta) {
                this.restoring = true;
                history.go(-this.delta);
            }
        },

        isDirty() {
            const now = this.formState();

            return now !== null && this.baseline !== null && now !== this.baseline;
        },

        back() {
            window.history.back();
        },

        leave() {
            this.confirming = false;
            this.baseline = null;
            window.Livewire.navigate(location.href);
        },
    }"
    x-init="remember()"
    x-on:alpine:navigate.document="onNavigate($event)"
    x-on:livewire:navigated.window="remember()"
    x-on:keydown.window="
        if (confirming) {
            if (event.key === 'Escape') { event.preventDefault(); stay(); return; }
            if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Tab'].includes(event.key)) {
                event.preventDefault();
                choice = choice === 'stay' ? 'leave' : 'stay';
                return;
            }
            if (event.key === 'Enter') { event.preventDefault(); event.stopPropagation(); choice === 'stay' ? stay() : leave(); return; }
        }

        // Ctrl/Cmd+S saves and Ctrl/Cmd+E cancels in the open modal (the
        // topmost one), e.g. changing a Status. Pages without a modal are
        // handled by Filament (S) and form-keyboard-shortcuts.blade.php (E).
        if (! (event.ctrlKey || event.metaKey) || event.altKey) return;
        const key = event.key.toLowerCase();
        if (key !== 's' && key !== 'e') return;

        const modals = document.querySelectorAll('.fi-modal.fi-modal-open');
        const modal = modals[modals.length - 1];
        if (! modal) return;

        const footer = modal.querySelector('.fi-modal-footer-actions');
        const submit = footer?.querySelector('button[type=submit]');
        const cancel = footer ? [...footer.querySelectorAll('button:not([type=submit])')].pop() : null;
        const target = key === 's' ? submit : cancel;
        if (! target) return;

        event.preventDefault();
        event.stopPropagation();
        target.click();
    "
    style="display: flex;"
>
    @if ($buttons ?? true)
    <x-filament::icon-button
        color="gray"
        icon="heroicon-o-arrow-left"
        icon-size="lg"
        :label="__('Back')"
        :tooltip="__('Back')"
        x-on:click="back()"
    />

    <x-filament::icon-button
        color="gray"
        icon="heroicon-o-arrow-right"
        icon-size="lg"
        :label="__('Forward')"
        :tooltip="__('Forward')"
        x-on:click="window.history.forward()"
    />
    @endif

    <template x-teleport="body">
        <div
            x-show="confirming"
            x-cloak
            x-transition.opacity
            style="position: fixed; inset: 0; z-index: 60; background: rgb(0 0 0 / 0.5);"
            role="alertdialog"
            aria-modal="true"
            x-on:click.self="stay()"
        >
            <div
                class="fi-section"
                style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: calc(100% - 2rem); max-width: 28rem; padding: 1.5rem; background: var(--color-white, #fff); border-radius: 0.75rem; box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.3);"
            >
                <div style="display: flex; gap: 1rem;">
                    <div style="flex: none; color: var(--warning-500, #f59e0b);">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" style="width: 2.5rem; height: 2.5rem;" />
                    </div>

                    <div>
                        <h2 style="font-weight: 600; font-size: 1.125rem;">{{ __('Do you want to leave the screen?') }}</h2>
                        <p style="margin-top: 0.25rem; opacity: 0.75;">{{ __('All changes will be lost.') }}</p>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                    <x-filament::button color="gray" x-on:click="stay()" x-on:mouseenter="choice = 'stay'" x-bind:style="choice === 'stay' && 'background: color-mix(in srgb, var(--primary-500) 18%, transparent); outline: 2px solid var(--primary-500); outline-offset: 1px;'" x-init="$watch('confirming', (v) => v && $nextTick(() => $el.focus()))">
                        {{ __('Stay') }}
                    </x-filament::button>

                    <x-filament::button color="gray" x-on:click="leave()" x-on:mouseenter="choice = 'leave'" x-bind:style="choice === 'leave' && 'background: color-mix(in srgb, var(--primary-500) 18%, transparent); outline: 2px solid var(--primary-500); outline-offset: 1px;'">
                        {{ __('Leave') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    </template>
</div>
