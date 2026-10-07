<?php

namespace Epesi\Modules\Currencies\Services;

use Carbon\CarbonImmutable;
use Epesi\Modules\Currencies\Models\CurrencyRate;
use Epesi\Modules\Currencies\Models\CurrencySetting;
use Epesi\Modules\Currencies\Services\Providers\RateProvider;
use Epesi\Modules\Currencies\Services\Providers\RateProviderException;

/**
 * Fills `currency_rates` from the configured provider: for each active
 * currency it publishes, from the day after its last cached rate (or the
 * backfill start) through today. A currency added later is backfilled on the
 * next run; the others only get the missing days.
 */
class RateFetcher
{
    public function __construct(
        protected RateProviders $providers,
        protected CurrencyRepository $currencies,
    ) {}

    /**
     * With no range this fills forward as described above. An explicit $from
     * (and $to, default today) fetches exactly that window for every active
     * currency, whatever is already cached: it is how a cutover date gets its
     * rates when the cache starts later. Stored rows are upserted, so a
     * repeat is harmless.
     *
     * @return array{provider: string, fetched: int, skipped: list<string>, error: ?string}
     */
    public function fetch(?string $providerId = null, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $setting = CurrencySetting::current();
        $provider = $providerId === null ? $this->providers->current() : $this->providers->for($providerId);
        $report = ['provider' => $provider->id(), 'fetched' => 0, 'skipped' => [], 'error' => null];

        try {
            $active = array_values(array_diff(array_keys($this->currencies->active()), [$provider->pivot()]));
            $supported = $provider->supported();
            $codes = array_values(array_intersect($active, $supported));
            $report['skipped'] = array_values(array_diff($active, $supported));

            $today = CarbonImmutable::today();
            $until = $to ?? $today;

            if ($from === null) {
                $backfillFrom = CarbonImmutable::parse($setting->backfill_from ?? $today->subYear()->startOfYear());
                $from = $this->startFor($provider, $codes, $backfillFrom);
            }

            if ($codes !== [] && $from->lte($until)) {
                $report['fetched'] = $this->store($provider, $provider->fetch($codes, $from, $until));
            }
        } catch (RateProviderException $e) {
            $report['error'] = $e->getMessage();
        }

        $setting->update(['last_fetch_at' => now(), 'last_fetch_result' => $this->describe($report)]);

        return $report;
    }

    /** The earliest day any of $codes is missing: the day after its last cached rate. */
    protected function startFor(RateProvider $provider, array $codes, CarbonImmutable $backfillFrom): CarbonImmutable
    {
        $start = null;

        foreach ($codes as $code) {
            $last = CurrencyRate::query()
                ->where('provider', $provider->id())
                ->where(fn ($query) => $query->where('base', $code)->orWhere('quote', $code))
                ->max('rate_date');

            $next = $last === null ? $backfillFrom : CarbonImmutable::parse($last)->addDay()->max($backfillFrom);
            $start = $start === null ? $next : $start->min($next);
        }

        return $start ?? CarbonImmutable::today();
    }

    /** @param  list<array{base: string, quote: string, rate_date: string, rate: float}>  $rows */
    protected function store(RateProvider $provider, array $rows): int
    {
        $now = now();

        foreach (array_chunk($rows, 500) as $chunk) {
            CurrencyRate::query()->upsert(
                array_map(fn (array $row): array => [...$row, 'provider' => $provider->id(), 'fetched_at' => $now, 'created_at' => $now, 'updated_at' => $now], $chunk),
                ['provider', 'base', 'quote', 'rate_date'],
                ['rate', 'fetched_at', 'updated_at'],
            );
        }

        return count($rows);
    }

    /** @param  array{provider: string, fetched: int, skipped: list<string>, error: ?string}  $report */
    public function describe(array $report): string
    {
        $text = $report['error'] ?? trans_choice('{0} No new exchange rates.|{1} Fetched :count exchange rate.|[2,*] Fetched :count exchange rates.', $report['fetched']);

        if ($report['skipped'] !== []) {
            $text .= ' '.__(':provider does not publish: :codes.', [
                'provider' => CurrencyRate::providers()[$report['provider']] ?? $report['provider'],
                'codes' => implode(', ', $report['skipped']),
            ]);
        }

        return $text;
    }
}
