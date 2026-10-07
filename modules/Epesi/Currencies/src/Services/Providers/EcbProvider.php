<?php

namespace Epesi\Modules\Currencies\Services\Providers;

use Carbon\CarbonInterface;
use Epesi\Modules\Currencies\Models\CurrencyRate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * ECB reference rates through Frankfurter (api.frankfurter.dev): free, no key.
 * The ECB publishes against EUR only, around 16:00 CET on TARGET business days,
 * so a pair like USD -> PLN is a cross rate of two EUR legs. These are
 * mid-market fixings, not the rate a bank actually applies.
 */
class EcbProvider implements RateProvider
{
    public const API_URL = 'https://api.frankfurter.dev/v1';

    /** Days per request, well inside what the API returns as daily data. */
    protected const CHUNK_DAYS = 365;

    public function id(): string
    {
        return CurrencyRate::ECB;
    }

    public function pivot(): string
    {
        return 'EUR';
    }

    public function strictlyBefore(): bool
    {
        return false;
    }

    public function supported(): array
    {
        $codes = array_keys((array) $this->get('/currencies')->json());

        return array_values(array_diff($codes, [$this->pivot()]));
    }

    public function fetch(array $codes, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($codes === []) {
            return [];
        }

        $rows = [];

        for ($start = $from->toImmutable(); $start->lte($to); $start = $start->addDays(self::CHUNK_DAYS)) {
            $end = $start->addDays(self::CHUNK_DAYS - 1)->min($to);

            $data = $this->get('/'.$start->toDateString().'..'.$end->toDateString(), [
                'base' => $this->pivot(),
                'symbols' => implode(',', $codes),
            ])->json('rates');

            foreach ((array) $data as $date => $rates) {
                foreach ((array) $rates as $code => $rate) {
                    if (in_array($code, $codes, true) && is_numeric($rate) && $rate > 0) {
                        $rows[] = ['base' => $this->pivot(), 'quote' => (string) $code, 'rate_date' => (string) $date, 'rate' => (float) $rate];
                    }
                }
            }
        }

        return $rows;
    }

    /** @param  array<string, string>  $query */
    protected function get(string $path, array $query = []): Response
    {
        try {
            $response = Http::timeout(30)->acceptJson()->get(self::API_URL.$path, $query);
        } catch (ConnectionException $e) {
            throw new RateProviderException(__('Could not reach :service.', ['service' => 'api.frankfurter.dev']), previous: $e);
        }

        if (! $response->successful()) {
            throw new RateProviderException(__(':service answered :status.', ['service' => 'api.frankfurter.dev', 'status' => $response->status()]));
        }

        return $response;
    }
}
