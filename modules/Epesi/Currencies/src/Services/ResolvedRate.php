<?php

namespace Epesi\Modules\Currencies\Services;

use Carbon\CarbonImmutable;

/**
 * An exchange rate and where it came from. `rate` converts one unit of the
 * from-currency into the to-currency: to_amount = from_amount × rate.
 */
final class ResolvedRate
{
    /** Typed on the document by a user. Always wins. */
    public const MANUAL = 'manual';

    /** Both currencies the same: the rate is 1, a fact rather than a lookup. */
    public const SAME = 'same';

    /** An administrator's rate from Exchange Rates (provider "custom"). */
    public const CUSTOM = 'custom';

    /** Nothing available. Callers leave the rate empty; they never substitute one. */
    public const NONE = 'none';

    // Otherwise the source is the provider's id: CurrencyRate::ECB or ::NBP.

    public function __construct(
        public readonly ?float $rate,
        public readonly string $source,
        public readonly ?CarbonImmutable $rateDate = null,
    ) {}

    public function found(): bool
    {
        return $this->rate !== null;
    }

    /** $amount in the to-currency, rounded to $decimals; null without a rate. */
    public function convert(float|int|string $amount, int $decimals = 2): ?float
    {
        return $this->rate === null ? null : round((float) $amount * $this->rate, $decimals);
    }
}
