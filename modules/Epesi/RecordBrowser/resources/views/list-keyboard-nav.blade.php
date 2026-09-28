{{--
    Keyboard-only browsing of a List page, for once "/" (command-palette.
    blade.php) has landed you on one: A/F/R jump straight to the All /
    Favorites / Recent tab (BrowseMode) when the resource has it, S focuses
    the table's own search field, N opens the "New …" page, ↑/↓ move a
    highlighted row the same way a mouse hovering it does (Enter opens it,
    the same place a click would), and PageUp/PageDown just move a page —
    the highlight starts over on the
    next ↑/↓ once that page has loaded, rather than chasing the same record
    across pages. Enter in the search field itself lands on the first result
    row the same way ↓ would, once the search has actually gone through, so
    Tab is never needed to get from typing a search to browsing its rows —
    and Tab no longer stops on the filter/column-manager triggers between
    the search field and the table, only on real content.

    Guarded the same way "/" is: never while typing into a field (search's
    own Enter handling is the one exception), and never while any modal
    (ours or Filament's own) is open over the table.
--}}
<div
    x-init="skipToolbarTabStops(); watchSearchEnter();"
    x-data="{
        rows() {
            return [...document.querySelectorAll('.fi-ta-row.fi-clickable')];
        },
        highlight(row) {
            this.rows().forEach((r) => r.classList.remove('epesi-row-active'));
            row?.classList.add('epesi-row-active');
            row?.scrollIntoView({ block: 'nearest' });
            this.activeRowKey = row?.getAttribute('wire:key') ?? null;
        },
        move(delta) {
            const rows = this.rows();

            if (! rows.length) return;

            const current = rows.findIndex((row) => row.getAttribute('wire:key') === this.activeRowKey);
            const next = Math.min(Math.max(current + delta, 0), rows.length - 1);

            this.highlight(rows[next]);
        },
        open() {
            this.rows()
                .find((row) => row.getAttribute('wire:key') === this.activeRowKey)
                ?.querySelector('a[href]')
                ?.click();
        },
        page(direction) {
            document.querySelector(direction < 0 ? '.fi-pagination-previous-btn' : '.fi-pagination-next-btn')?.click();
            this.highlight(null);
        },
        {{--
            The toolbar row is search field, then the filter trigger, then
            the column manager trigger (index.blade.php), all siblings under
            one wrapping div — Tab from the search field otherwise stops on
            both of those before ever reaching a row. Leaves them clickable,
            just out of the tab order. The filter trigger's own wire:key
            changes with its active-filters state (AppServiceProvider's
            epesi-no-active-filters class), so Livewire replaces that node
            outright rather than patching it — a one-time fix on it, or an
            observer watching only its old node, goes stale the moment
            switching tabs (which filters too, via BrowseMode) first touches
            it. Watching the whole document body survives that, since it is
            never itself the node being replaced.
        --}}
        skipToolbarTabStops() {
            const searchField = document.querySelector('.fi-ta-search-field');

            if (! searchField) return;

            const apply = () => {
                const current = document.querySelector('.fi-ta-search-field');
                const siblings = [...(current?.parentElement.children ?? [])];

                siblings.slice(siblings.indexOf(current) + 1).forEach((sibling) => {
                    sibling.querySelectorAll('a, button, [tabindex]').forEach((el) => el.setAttribute('tabindex', '-1'));

                    if (sibling.matches('a, button, [tabindex]')) sibling.setAttribute('tabindex', '-1');
                });
            };

            apply();
            new MutationObserver(apply).observe(document.body, { childList: true, subtree: true });
        },
        {{--
            Filament's own search field (search-field.blade.php) forces its
            debounced search early on keyup Enter, but leaves the debounce
            timer running — typing fast enough that it is still pending when
            Enter lands (a real "type it, hit Enter" speed, not just a fast
            typist) fires the table's update twice, close enough together to
            corrupt each other's row selection state (Alpine errors, an
            empty table). Catching Enter on window, in the capture phase,
            runs this before the event ever reaches the field's own keyup
            listener — capturing listeners on an ancestor always go first,
            unlike two listeners on the field itself, where trying to attach
            before Filament's own turned out not to reliably win — and
            stopping it there keeps that keyup from reaching the field at
            all, so only the debounce's own single request happens. Landing
            on the first row and leaving the field (so ↓/Enter work
            immediately with no Tab needed) waits until the table has gone
            quiet for 300ms, or 1.5s of nothing happening at all for a
            search that came back the same as before (nothing left pending
            to wait for).
        --}}
        watchSearchEnter() {
            const input = document.querySelector('.fi-ta-search-field input');
            const tbody = input?.closest('.fi-ta')?.querySelector('tbody');

            if (! input || ! tbody) return;

            window.addEventListener('keyup', (event) => {
                if (event.key !== 'Enter' || event.target !== input) return;

                event.stopPropagation();

                const land = () => {
                    observer.disconnect();
                    this.highlight(this.rows()[0] ?? null);
                    input.blur();
                };
                let settle = setTimeout(land, 1500);
                const observer = new MutationObserver(() => {
                    clearTimeout(settle);
                    settle = setTimeout(land, 300);
                });

                observer.observe(tbody, { childList: true, subtree: true });
            }, true);
        },
        activeRowKey: null,
    }"
    x-on:keydown.window="
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName) || event.target.isContentEditable) return;
        if (document.querySelector('.fi-modal.fi-modal-open')) return;

        if (event.key === 'ArrowDown') { event.preventDefault(); move(1); }
        else if (event.key === 'ArrowUp') { event.preventDefault(); move(-1); }
        else if (event.key === 'PageDown') { event.preventDefault(); page(1); }
        else if (event.key === 'PageUp') { event.preventDefault(); page(-1); }
        else if (event.key === 'Enter' && activeRowKey !== null) { event.preventDefault(); open(); }
        else if (event.key.toLowerCase() === 's') { event.preventDefault(); document.querySelector('.fi-ta-search-field input')?.focus(); }
        else if (event.key.toLowerCase() === 'n') { event.preventDefault(); document.querySelector('a[href$=\'/create\']')?.click(); }
        @foreach ($tabKeys as $key)
        else if (event.key.toLowerCase() === '{{ substr($key, 0, 1) }}') { event.preventDefault(); $wire.set('activeTab', '{{ $key }}'); }
        @endforeach
    "
></div>
