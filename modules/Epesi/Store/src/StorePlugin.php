<?php

namespace Epesi\Modules\Store;

use Epesi\Modules\Store\Filament\Pages\Store;
use Epesi\Modules\Store\Models\StoreSetting;
use Epesi\Modules\Store\Services\StoreClient;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;

class StorePlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-store';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            Store::class,
        ]);

        // A warning bar while the store's last answer came from a test copy (a
        // hosts-file entry pointing store.epe.si at a local store, say).
        $panel->renderHook(PanelsRenderHook::BODY_END, fn (): string => self::isTestStore()
            ? view('epesi-store::test-store-banner', ['host' => parse_url(StoreClient::API_URL, PHP_URL_HOST)])->render()
            : '');
    }

    public function boot(Panel $panel): void {}

    /** False before the module's tables exist (a fresh install, mid-update). */
    protected static function isTestStore(): bool
    {
        return rescue(fn (): bool => StoreSetting::query()->value('store_environment') === 'test', false, report: false);
    }
}
