<?php

namespace Epesi\Modules\Currencies\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Epesi\Modules\Currencies\Models\CurrencyRate;
use Epesi\Modules\Currencies\Services\Providers\RateProvider;

/**
 * Which rate a document dated D, in currency A, is booked at in currency B.
 * The one place the precedence lives; modules call this rather than reading
 * `currency_rates` themselves.
 *
 *   1. a rate typed on the document (manual): always wins, because a bank's
 *      rate isn't the central bank's;
 *   2. A = B: 1 (same);
 *   3. the configured provider (ecb, nbp), derived through its pivot: for the
 *      ECB, USD -> PLN = (EUR -> PLN) / (EUR -> USD). The latest rate on or
 *      before D, or strictly before D for the NBP, no older than MAX_AGE_DAYS;
 *   4. an administrator's custom rate for the pair, either direction, the
 *      latest on or before D: for currencies the provider doesn't publish;
 *   5. none. Never today's rate in place of a historical one.
 *
 * Direction: to_amount = from_amount × rate. No reciprocal anywhere else.
 */
class RateResolver
{
    /**
     * A provider rate older than this before D is not used: it means the cache
     * stopped being filled, not that the market was closed (Easter is 4 days).
     */
    public const MAX_AGE_DAYS = 7;

    public function __construct(protected RateProviders $providers) {}

    public function resolve(string $from, string $to, CarbonInterface|string|null $date = null, float|int|string|null $manualRate = null, ?string $provider = null): ResolvedRate
    {
        if (is_numeric($manualRate) && (float) $manualRate > 0) {
            return new ResolvedRate((float) $manualRate, ResolvedRate::MANUAL);
        }

        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return new ResolvedRate(1.0, ResolvedRate::SAME);
        }

        $date = CarbonImmutable::parse($date ?? now())->startOfDay();

        return $this->fromProvider($provider === null ? $this->providers->current() : $this->providers->for($provider), $from, $to, $date)
            ?? $this->fromCustom($from, $to, $date)
            ?? new ResolvedRate(null, ResolvedRate::NONE);
    }

    protected function fromProvider(RateProvider $provider, string $from, string $to, CarbonImmutable $date): ?ResolvedRate
    {
        $cutoff = $provider->strictlyBefore() ? $date->subDay() : $date;

        $a = $this->pivotPerUnit($provider, $from, $cutoff);
        $b = $this->pivotPerUnit($provider, $to, $cutoff);

        if ($a === null || $b === null) {
            return null;
        }

        // The older of the two legs is the date the cross rate stands for.
        $rateDate = collect([$a[1], $b[1]])->filter()->sort()->first();

        if ($rateDate !== null && $rateDate->lt($cutoff->subDays(self::MAX_AGE_DAYS))) {
            return null;
        }

        return new ResolvedRate($a[0] / $b[0], $provider->id(), $rateDate);
    }

    /**
     * How many units of the provider's pivot one unit of $code buys, and the
     * date of that rate (null for the pivot itself).
     *
     * @return array{0: float, 1: ?CarbonImmutable}|null
     */
    protected function pivotPerUnit(RateProvider $provider, string $code, CarbonImmutable $cutoff): ?array
    {
        $pivot = $provider->pivot();

        if ($code === $pivot) {
            return [1.0, null];
        }

        $row = CurrencyRate::query()
            ->where('provider', $provider->id())
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('base', $code)->where('quote', $pivot))
                ->orWhere(fn ($query) => $query->where('base', $pivot)->where('quote', $code)))
            ->whereDate('rate_date', '<=', $cutoff->toDateString())
            ->orderByDesc('rate_date')
            ->first();

        if ($row === null || $row->rate <= 0) {
            return null;
        }

        return [$row->base === $code ? $row->rate : 1 / $row->rate, CarbonImmutable::parse($row->rate_date)];
    }

    protected function fromCustom(string $from, string $to, CarbonImmutable $date): ?ResolvedRate
    {
        $row = CurrencyRate::query()
            ->where('provider', CurrencyRate::CUSTOM)
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('base', $from)->where('quote', $to))
                ->orWhere(fn ($query) => $query->where('base', $to)->where('quote', $from)))
            ->whereDate('rate_date', '<=', $date->toDateString())
            ->orderByDesc('rate_date')
            ->first();

        if ($row === null || $row->rate <= 0) {
            return null;
        }

        return new ResolvedRate($row->base === $from ? $row->rate : 1 / $row->rate, ResolvedRate::CUSTOM, CarbonImmutable::parse($row->rate_date));
    }
}
