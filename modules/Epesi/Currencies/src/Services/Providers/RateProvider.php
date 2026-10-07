<?php

namespace Epesi\Modules\Currencies\Services\Providers;

use Carbon\CarbonInterface;

/**
 * A source of daily reference rates. Each publishes against one pivot
 * currency, and RateResolver derives every other pair through it, so the
 * cache holds one row per currency per day instead of one per pair.
 */
interface RateProvider
{
    /** CurrencyRate::ECB, CurrencyRate::NBP */
    public function id(): string;

    /** The currency every published rate is against: EUR for the ECB, PLN for the NBP. */
    public function pivot(): string;

    /**
     * Whether a document dated D takes the last rate published strictly before
     * D (Polish VAT, Art. 31a: the NBP rate of the last business day before the
     * tax point) rather than the one published on D itself.
     */
    public function strictlyBefore(): bool;

    /**
     * The codes, other than the pivot, this provider publishes.
     *
     * @return list<string>
     *
     * @throws RateProviderException
     */
    public function supported(): array;

    /**
     * Published rates for $codes (all supported, none the pivot) from $from to
     * $to inclusive.
     *
     * @param  list<string>  $codes
     * @return list<array{base: string, quote: string, rate_date: string, rate: float}>
     *
     * @throws RateProviderException
     */
    public function fetch(array $codes, CarbonInterface $from, CarbonInterface $to): array;
}
