<?php

namespace Epesi\Modules\Currencies\Services\Providers;

use Carbon\CarbonInterface;
use Epesi\Modules\Currencies\Models\CurrencyRate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * National Bank of Poland, table A (average rates, published each Polish
 * business day around 12:15 CET): 1 unit of a currency = `mid` PLN.
 *
 * A Polish VAT document dated D is converted at the rate of the last business
 * day before D, never D itself (strictlyBefore()).
 */
class NbpProvider implements RateProvider
{
    public const API_URL = 'https://api.nbp.pl/api/exchangerates/tables/A';

    /** The API refuses ranges over 93 days. */
    protected const CHUNK_DAYS = 90;

    public function id(): string
    {
        return CurrencyRate::NBP;
    }

    public function pivot(): string
    {
        return 'PLN';
    }

    public function strictlyBefore(): bool
    {
        return true;
    }

    public function supported(): array
    {
        $tables = $this->get('/')?->json() ?? [];

        return array_values(array_map(
            fn (array $rate): string => (string) $rate['code'],
            $tables[0]['rates'] ?? [],
        ));
    }

    public function fetch(array $codes, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($codes === []) {
            return [];
        }

        $rows = [];

        for ($start = $from->toImmutable(); $start->lte($to); $start = $start->addDays(self::CHUNK_DAYS)) {
            $end = $start->addDays(self::CHUNK_DAYS - 1)->min($to);

            // A 404 is the API's "no table in this range" (a weekend, a holiday).
            foreach ($this->get('/'.$start->toDateString().'/'.$end->toDateString().'/')?->json() ?? [] as $table) {
                foreach ($table['rates'] ?? [] as $rate) {
                    if (in_array($rate['code'] ?? null, $codes, true) && is_numeric($rate['mid'] ?? null) && $rate['mid'] > 0) {
                        $rows[] = ['base' => (string) $rate['code'], 'quote' => $this->pivot(), 'rate_date' => (string) $table['effectiveDate'], 'rate' => (float) $rate['mid']];
                    }
                }
            }
        }

        return $rows;
    }

    /** Null for the API's 404, which means "no data", not an error. */
    protected function get(string $path): ?Response
    {
        try {
            $response = Http::timeout(30)->acceptJson()->get(self::API_URL.$path, ['format' => 'json']);
        } catch (ConnectionException $e) {
            throw new RateProviderException(__('Could not reach :service.', ['service' => 'api.nbp.pl']), previous: $e);
        }

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new RateProviderException(__(':service answered :status.', ['service' => 'api.nbp.pl', 'status' => $response->status()]));
        }

        return $response;
    }
}
