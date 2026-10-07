<?php

namespace Tests\Feature\Modules;

use Carbon\CarbonImmutable;
use Epesi\Modules\Currencies\Filament\Resources\Currencies\CurrencyResource;
use Epesi\Modules\Currencies\Filament\Resources\Currencies\Pages\ListCurrencies;
use Epesi\Modules\Currencies\Filament\Resources\ExchangeRates\ExchangeRateResource;
use Epesi\Modules\Currencies\Filament\Resources\ExchangeRates\Pages\ListExchangeRates;
use Epesi\Modules\Currencies\Filament\Widgets\CurrencyConverterWidget;
use Epesi\Modules\Currencies\LegacyImport\CurrenciesImporter;
use Epesi\Modules\Currencies\Models\Currency;
use Epesi\Modules\Currencies\Models\CurrencyRate;
use Epesi\Modules\Currencies\Models\CurrencySetting;
use Epesi\Modules\Currencies\Services\CurrencyRepository;
use Epesi\Modules\Currencies\Services\RateFetcher;
use Epesi\Modules\Currencies\Services\RateFormatter;
use Epesi\Modules\Currencies\Services\RateProviders;
use Epesi\Modules\Currencies\Services\RateResolver;
use Epesi\Modules\Currencies\Services\ResolvedRate;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\SignsInUsers;
use Tests\TestCase;

/**
 * Currencies, the dated home currency, the ECB/NBP rate cache and the
 * resolver every accounting module books its rates through. The rate APIs are
 * faked.
 */
class CurrenciesTest extends TestCase
{
    use RefreshDatabase, SignsInUsers;

    private function repository(): CurrencyRepository
    {
        return app(CurrencyRepository::class);
    }

    private function rate(string $provider, string $base, string $quote, string $date, float $rate): void
    {
        CurrencyRate::query()->create(compact('provider', 'base', 'quote', 'rate') + ['rate_date' => $date]);
    }

    public function test_install_seeds_four_currencies_with_usd_as_home(): void
    {
        $this->assertSame(['USD', 'EUR', 'GBP', 'PLN'], array_keys($this->repository()->active()));
        $this->assertSame('USD', $this->repository()->home());
        $this->assertSame(2, $this->repository()->decimals('PLN'));
        $this->assertSame(0, $this->repository()->decimals('JPY'), 'ISO minor units for a currency not in the table');
        $this->assertSame(3, $this->repository()->decimals('KWD'));
    }

    public function test_the_home_currency_is_a_dated_fact(): void
    {
        $this->repository()->setHome('PLN');
        $this->repository()->setHome('USD', '2020-01-01');

        $this->assertSame('PLN', $this->repository()->home('2016-05-10'));
        $this->assertSame('USD', $this->repository()->home('2020-01-01'));
        $this->assertSame('USD', $this->repository()->home());
    }

    public function test_the_provider_follows_the_home_currency_until_one_is_chosen(): void
    {
        $providers = app(RateProviders::class);
        $this->assertSame(RateProviders::AUTOMATIC, CurrencySetting::current()->rate_provider);
        $this->assertSame(CurrencyRate::ECB, $providers->currentId());

        $this->repository()->setHome('PLN');
        $this->assertSame(CurrencyRate::NBP, $providers->currentId(), 'Polish VAT wants the NBP rate');

        CurrencySetting::current()->update(['rate_provider' => CurrencyRate::ECB]);
        $this->assertSame(CurrencyRate::ECB, $providers->currentId(), 'a provider chosen in Settings stays');
    }

    public function test_ecb_rates_are_cross_rates_through_the_euro(): void
    {
        $this->rate(CurrencyRate::ECB, 'EUR', 'USD', '2026-10-01', 1.1298);
        $this->rate(CurrencyRate::ECB, 'EUR', 'PLN', '2026-10-01', 4.3735);

        $rate = app(RateResolver::class)->resolve('USD', 'PLN', '2026-10-01');

        $this->assertSame(CurrencyRate::ECB, $rate->source);
        $this->assertEqualsWithDelta(4.3735 / 1.1298, $rate->rate, 1e-12);
        $this->assertSame('2026-10-01', $rate->rateDate->toDateString());
        $this->assertEqualsWithDelta(1 / 4.3735, app(RateResolver::class)->resolve('PLN', 'EUR', '2026-10-01')->rate, 1e-12);

        // A Saturday takes Friday's rate, the latest on or before the date.
        $this->assertSame('2026-10-01', app(RateResolver::class)->resolve('USD', 'PLN', '2026-10-03')->rateDate->toDateString());
    }

    public function test_nbp_uses_the_rate_published_strictly_before_the_document_date(): void
    {
        CurrencySetting::current()->update(['rate_provider' => CurrencyRate::NBP]);

        $this->rate(CurrencyRate::NBP, 'USD', 'PLN', '2026-10-01', 3.8762); // Thursday
        $this->rate(CurrencyRate::NBP, 'USD', 'PLN', '2026-10-02', 3.8800); // Friday
        $this->rate(CurrencyRate::NBP, 'USD', 'PLN', '2026-10-05', 3.9000); // Monday

        $friday = app(RateResolver::class)->resolve('USD', 'PLN', '2026-10-02');
        $this->assertSame(3.8762, $friday->rate, 'a Friday document takes Thursday\'s table');

        $monday = app(RateResolver::class)->resolve('USD', 'PLN', '2026-10-05');
        $this->assertSame(3.8800, $monday->rate, 'a Monday document takes Friday\'s table, over the weekend');
        $this->assertSame(CurrencyRate::NBP, $monday->source);
    }

    public function test_resolution_order_and_no_invented_rate(): void
    {
        $resolver = app(RateResolver::class);

        $this->assertSame(ResolvedRate::MANUAL, $resolver->resolve('USD', 'PLN', '2026-10-01', manualRate: '3.95')->source);
        $this->assertSame(ResolvedRate::SAME, $resolver->resolve('PLN', 'pln')->source);

        $none = $resolver->resolve('USD', 'PLN', '2026-10-01');
        $this->assertSame(ResolvedRate::NONE, $none->source);
        $this->assertNull($none->rate);
        $this->assertNull($none->convert(100));

        // A custom rate fills a pair the provider doesn't publish, in either direction.
        $this->rate(CurrencyRate::CUSTOM, 'PLN', 'USD', '2026-09-01', 0.25);
        $custom = $resolver->resolve('USD', 'PLN', '2026-10-01');
        $this->assertSame(ResolvedRate::CUSTOM, $custom->source);
        $this->assertSame(4.0, $custom->rate);
        $this->assertSame(400.0, $custom->convert(100));

        // A provider rate wins over the custom one, but not a stale one.
        $this->rate(CurrencyRate::ECB, 'EUR', 'USD', '2026-09-01', 1.1);
        $this->rate(CurrencyRate::ECB, 'EUR', 'PLN', '2026-09-01', 4.3);
        $this->assertSame(ResolvedRate::CUSTOM, $resolver->resolve('USD', 'PLN', '2026-10-01')->source, 'a month-old provider rate means the cache stopped');
        $this->assertSame(CurrencyRate::ECB, $resolver->resolve('USD', 'PLN', '2026-09-02')->source);
    }

    public function test_fetching_from_the_ecb_stores_only_what_it_publishes(): void
    {
        CurrencySetting::current()->update(['backfill_from' => '2026-10-01']);
        $this->travelTo('2026-10-02 18:00:00');

        Http::fake([
            'api.frankfurter.dev/v1/currencies' => Http::response(['EUR' => 'Euro', 'USD' => 'US Dollar', 'GBP' => 'British Pound', 'PLN' => 'Polish Zloty']),
            'api.frankfurter.dev/v1/2026-10-01..2026-10-02*' => Http::response(['base' => 'EUR', 'rates' => [
                '2026-10-01' => ['USD' => 1.1298, 'GBP' => 0.87, 'PLN' => 4.3735],
                '2026-10-02' => ['USD' => 1.1225, 'GBP' => 0.86, 'PLN' => 4.3775],
            ]]),
        ]);

        $report = app(RateFetcher::class)->fetch();

        $this->assertNull($report['error']);
        $this->assertSame(6, $report['fetched']);
        $this->assertSame(6, CurrencyRate::query()->where('base', 'EUR')->count(), 'one row per currency per day, no derived pairs');
        $this->assertEqualsWithDelta(4.3775 / 1.1225, app(RateResolver::class)->resolve('USD', 'PLN', '2026-10-02')->rate, 1e-12);

        // The next run asks only for the missing days, and there are none.
        Http::fake(['api.frankfurter.dev/v1/currencies' => Http::response(['EUR' => 'Euro', 'USD' => 'US Dollar', 'GBP' => 'British Pound', 'PLN' => 'Polish Zloty'])]);
        $this->assertSame(0, app(RateFetcher::class)->fetch()['fetched']);
    }

    public function test_fetching_a_period_reaches_back_before_the_cached_rates(): void
    {
        $this->travelTo('2026-10-02 18:00:00');
        CurrencyRate::query()->create(['provider' => CurrencyRate::ECB, 'base' => 'EUR', 'quote' => 'USD', 'rate_date' => '2026-10-01', 'rate' => 1.12]);
        CurrencyRate::query()->create(['provider' => CurrencyRate::ECB, 'base' => 'EUR', 'quote' => 'PLN', 'rate_date' => '2026-10-01', 'rate' => 4.37]);
        CurrencyRate::query()->create(['provider' => CurrencyRate::ECB, 'base' => 'EUR', 'quote' => 'GBP', 'rate_date' => '2026-10-01', 'rate' => 0.87]);

        Http::fake([
            'api.frankfurter.dev/v1/currencies' => Http::response(['EUR' => 'Euro', 'USD' => 'US Dollar', 'GBP' => 'British Pound', 'PLN' => 'Polish Zloty']),
            'api.frankfurter.dev/v1/2025-12-30..2026-01-02*' => Http::response(['base' => 'EUR', 'rates' => [
                '2025-12-31' => ['USD' => 1.175, 'GBP' => 0.8726, 'PLN' => 4.2267],
                '2026-01-02' => ['USD' => 1.1721, 'GBP' => 0.8719, 'PLN' => 4.2156],
            ]]),
        ]);

        $report = app(RateFetcher::class)->fetch(null, CarbonImmutable::parse('2025-12-30'), CarbonImmutable::parse('2026-01-02'));

        $this->assertNull($report['error']);
        $this->assertSame(6, $report['fetched']);
        $this->assertEqualsWithDelta(4.2267 / 1.175, app(RateResolver::class)->resolve('USD', 'PLN', '2026-01-01')->rate, 1e-12);

        // The same window again changes nothing: rows are upserted.
        app(RateFetcher::class)->fetch(null, CarbonImmutable::parse('2025-12-30'), CarbonImmutable::parse('2026-01-02'));
        $this->assertSame(9, CurrencyRate::query()->count());
    }

    public function test_fetching_from_the_nbp_reports_what_it_does_not_publish(): void
    {
        CurrencySetting::current()->update(['rate_provider' => CurrencyRate::NBP, 'backfill_from' => '2026-10-01']);
        Currency::query()->create(['code' => 'XCG', 'name' => 'Caribbean Guilder']);
        $this->travelTo('2026-10-01 18:00:00');

        Http::fake([
            'api.nbp.pl/api/exchangerates/tables/A/2026-10-01/2026-10-01/*' => Http::response([
                ['table' => 'A', 'effectiveDate' => '2026-10-01', 'rates' => [['code' => 'USD', 'mid' => 3.8762], ['code' => 'EUR', 'mid' => 4.2510], ['code' => 'GBP', 'mid' => 5.0]]],
            ]),
            'api.nbp.pl/api/exchangerates/tables/A/*' => Http::response([
                ['table' => 'A', 'effectiveDate' => '2026-10-01', 'rates' => [['code' => 'USD', 'mid' => 3.8762], ['code' => 'EUR', 'mid' => 4.2510], ['code' => 'GBP', 'mid' => 5.0]]],
            ]),
        ]);

        $report = app(RateFetcher::class)->fetch();

        $this->assertSame(3, $report['fetched']);
        $this->assertSame(['XCG'], $report['skipped']);
        $this->assertSame(3, CurrencyRate::query()->where('provider', CurrencyRate::NBP)->where('quote', 'PLN')->count());
        $this->assertStringContainsString('XCG', CurrencySetting::current()->last_fetch_result);
    }

    public function test_a_provider_failure_is_reported_not_thrown(): void
    {
        Http::fake(['api.frankfurter.dev/*' => Http::response('down', 503)]);

        $report = app(RateFetcher::class)->fetch();

        $this->assertNotNull($report['error']);
        $this->assertSame(0, CurrencyRate::query()->count());
    }

    public function test_rates_are_formatted_by_significant_digits(): void
    {
        $this->assertSame('0.0000578', RateFormatter::format(0.0000578));
        $this->assertSame('25.34', RateFormatter::format(25.34));
        $this->assertSame('1.00', RateFormatter::format(1));
        $this->assertSame('3.87104', RateFormatter::format(3.871039));
        $this->assertSame('', RateFormatter::format(null));
    }

    public function test_the_administration_pages_work_for_a_super_admin(): void
    {
        $this->actingAs($this->userWithRole('super_admin'));
        Filament::setCurrentPanel('administration');

        Livewire::test(ListCurrencies::class)
            ->assertOk()
            ->assertSee('PLN')
            ->callAction('setHome', ['code' => 'PLN', 'effective_from' => null])
            ->assertHasNoActionErrors();

        $this->assertSame('PLN', $this->repository()->home('2001-01-01'));

        Livewire::test(ListCurrencies::class)
            ->callAction('create', ['code' => 'CHF', 'name' => 'Swiss Franc', 'decimals' => 2, 'active' => true])
            ->assertHasNoActionErrors();
        $this->assertTrue($this->repository()->isActive('CHF'));

        Livewire::test(ListExchangeRates::class)
            ->assertOk()
            ->callAction('settings', ['rate_provider' => CurrencyRate::NBP, 'auto_fetch' => false, 'backfill_from' => '2026-01-01'])
            ->assertHasNoActionErrors();
        $this->assertSame(CurrencyRate::NBP, CurrencySetting::current()->rate_provider);

        Http::fake(['api.frankfurter.dev/*' => Http::response('down', 503)]);
        Livewire::test(ListExchangeRates::class)
            ->callAction('fetchRange', ['from' => '2026-01-01', 'to' => null])
            ->assertHasNoActionErrors();

        Livewire::test(ListExchangeRates::class)
            ->mountAction('convert')
            ->assertSee(__('Currency Converter'), false);
    }

    public function test_the_currency_converter_applet_resolves_the_rate_of_the_chosen_day(): void
    {
        $this->actingAs($this->userWithRole('employee'));
        $this->rate(CurrencyRate::ECB, 'EUR', 'USD', '2026-10-01', 1.1298);
        $this->rate(CurrencyRate::ECB, 'EUR', 'PLN', '2026-10-01', 4.3735);

        $this->assertTrue(CurrencyConverterWidget::canView());
        $this->assertContains(CurrencyConverterWidget::class, Filament::getPanel('main')->getWidgets());

        $applet = Livewire::test(CurrencyConverterWidget::class, ['appletSettings' => ['from' => 'USD', 'to' => 'PLN']])
            ->assertOk()
            ->assertSet('from', 'USD')
            ->assertSet('to', 'PLN')
            ->set('date', '2026-10-02')
            ->assertSet('source', 'ECB, 2026-10-01')
            ->assertSee('1 USD = '.RateFormatter::format(4.3735 / 1.1298).' PLN');
        $this->assertEqualsWithDelta(4.3735 / 1.1298, $applet->get('rate'), 1e-12);

        $applet->call('swap')
            ->assertSet('from', 'PLN')
            ->assertSet('to', 'USD');
        $this->assertEqualsWithDelta(1.1298 / 4.3735, $applet->get('rate'), 1e-12);

        // No rate that day: the display says so rather than showing a number.
        $applet->set('date', '2020-01-01')->assertSet('rate', null)->assertSee('No rate for PLN → USD on 2020-01-01.');

        // An inactive or made-up currency from the browser falls back to the home currency.
        $applet->set('from', 'XXX')->assertSet('from', 'USD');
    }

    public function test_the_administration_pages_are_closed_to_a_manager(): void
    {
        $this->actingAs($this->userWithRole('manager'));

        $this->get(CurrencyResource::getUrl('index', panel: 'administration'))->assertForbidden();
        $this->get(ExchangeRateResource::getUrl('index', panel: 'administration'))->assertForbidden();
    }

    public function test_the_legacy_currency_list_is_imported(): void
    {
        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('legacy');

        Schema::connection('legacy')->create('utils_currency', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->nullable();
            $table->string('code');
            $table->integer('decimals')->nullable();
            $table->integer('active');
            $table->integer('default_currency');
        });
        DB::connection('legacy')->table('utils_currency')->insert([
            ['symbol' => '$', 'code' => 'USD', 'decimals' => 2, 'active' => 1, 'default_currency' => 0],
            ['symbol' => 'zł', 'code' => 'PLN', 'decimals' => 2, 'active' => 1, 'default_currency' => 1],
            ['symbol' => 'Kč', 'code' => 'czk', 'decimals' => 2, 'active' => 0, 'default_currency' => 0],
            ['symbol' => '?', 'code' => 'Złoto', 'decimals' => 2, 'active' => 1, 'default_currency' => 0],
        ]);

        $summary = (new CurrenciesImporter)->run();

        $this->assertSame(1, $summary->created);
        $this->assertSame(2, $summary->updated);
        $this->assertCount(1, $summary->warnings);
        $this->assertSame('Czech Koruna', Currency::query()->where('code', 'CZK')->value('name'));
        $this->assertFalse($this->repository()->isActive('CZK'));
        $this->assertSame('PLN', $this->repository()->home('2010-01-01'));
    }
}
