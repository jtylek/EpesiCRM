{{-- The store's last answer said it is a test copy (StoreSetting store_environment = 'test'). Fixed to the
     bottom of every administration page; the spacer keeps it from covering the end of the page. --}}
<div style="height: 2.75rem" aria-hidden="true"></div>
<div role="alert"
     style="position: fixed; inset: auto 0 0 0; z-index: 50; display: flex; gap: .5rem; align-items: center; justify-content: center; padding: .6rem 1rem; background: #f59e0b; color: #1c1917; font-size: .875rem; font-weight: 600; text-align: center; box-shadow: 0 -2px 6px rgb(0 0 0 / .2);">
    <x-filament::icon icon="heroicon-m-exclamation-triangle" style="width: 1.25rem; height: 1.25rem; flex: none;" />
    <span>{{ __('Connected to a TEST Epesi Store server (:host), not the real store. Registrations, licences and downloads from it are not real.', ['host' => $host]) }}</span>
</div>
