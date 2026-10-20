<?php

namespace Tests\Feature\Modules;

use App\Filament\Administration\Pages\About;
use Epesi\Modules\Store\Models\StoreSetting;
use Epesi\Modules\Store\Services\StoreClient;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * The store says on every answer whether it is the real store or a test copy
 * (X-Epesi-Store-Environment). A test copy, e.g. reached through a hosts-file
 * entry for store.epe.si, puts a warning bar on the administration panel.
 */
class StoreEnvironmentTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    /** StoreClient::API_URL — a literal: module classes load only once the app boots. */
    private const API = 'https://store.epe.si/manage/store-api';

    private const BANNER = 'Connected to a TEST Epesi Store server';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');
    }

    public function test_a_test_store_answer_is_remembered_and_shows_the_bar(): void
    {
        Http::fake([self::API.'/catalog' => Http::response(['modules' => [], 'core' => null], 200, ['X-Epesi-Store-Environment' => 'test'])]);

        app(StoreClient::class)->catalog();

        $this->assertSame('test', StoreSetting::current()->store_environment);
        $this->get(About::getUrl(panel: 'administration'))->assertOk()->assertSee(self::BANNER)->assertSee('store.epe.si');
    }

    public function test_the_production_store_shows_no_bar(): void
    {
        StoreSetting::current()->forceFill(['store_environment' => 'test'])->save();
        Http::fake([self::API.'/catalog' => Http::response(['modules' => [], 'core' => null], 200, ['X-Epesi-Store-Environment' => 'production'])]);

        app(StoreClient::class)->catalog();

        $this->assertSame('production', StoreSetting::current()->store_environment);
        $this->get(About::getUrl(panel: 'administration'))->assertOk()->assertDontSee(self::BANNER);
    }

    public function test_an_error_answer_from_a_test_store_still_counts(): void
    {
        Http::fake([self::API.'/catalog' => Http::response(['error' => 'unregistered'], 403, ['X-Epesi-Store-Environment' => 'test'])]);

        rescue(fn () => app(StoreClient::class)->catalog(), report: false);

        $this->assertSame('test', StoreSetting::current()->store_environment);
    }

    public function test_a_store_that_does_not_say_clears_it(): void
    {
        StoreSetting::current()->forceFill(['store_environment' => 'test'])->save();
        Http::fake([self::API.'/catalog' => Http::response(['modules' => [], 'core' => null])]);

        app(StoreClient::class)->catalog();

        $this->assertNull(StoreSetting::current()->store_environment);
        $this->get(About::getUrl(panel: 'administration'))->assertOk()->assertDontSee(self::BANNER);
    }
}
