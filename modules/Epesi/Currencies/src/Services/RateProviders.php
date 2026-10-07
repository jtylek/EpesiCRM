<?php

namespace Epesi\Modules\Currencies\Services;

use Epesi\Modules\Currencies\Models\CurrencyRate;
use Epesi\Modules\Currencies\Models\CurrencySetting;
use Epesi\Modules\Currencies\Services\Providers\EcbProvider;
use Epesi\Modules\Currencies\Services\Providers\NbpProvider;
use Epesi\Modules\Currencies\Services\Providers\RateProvider;
use InvalidArgumentException;

/**
 * The daily-rate providers by id. Another provider is one class here and one
 * entry in CurrencyRate::providers().
 */
class RateProviders
{
    /**
     * The setting that picks by the home currency, the default: NBP when it's
     * the Polish złoty (Polish VAT wants the NBP rate), ECB otherwise.
     */
    public const AUTOMATIC = 'auto';

    /** @var array<string, class-string<RateProvider>> */
    protected const PROVIDERS = [
        CurrencyRate::ECB => EcbProvider::class,
        CurrencyRate::NBP => NbpProvider::class,
    ];

    public function for(string $id): RateProvider
    {
        $class = self::PROVIDERS[$id] ?? throw new InvalidArgumentException("Unknown exchange rate provider \"{$id}\".");

        return app($class);
    }

    /** The provider chosen in Exchange Rates → Settings. */
    public function current(): RateProvider
    {
        return $this->for($this->currentId());
    }

    /** The id of the provider in use, with Automatic resolved. */
    public function currentId(): string
    {
        $id = rescue(fn () => CurrencySetting::current()->rate_provider, self::AUTOMATIC, report: false);

        if ($id === self::AUTOMATIC) {
            return rescue(fn () => app(CurrencyRepository::class)->home(), null, report: false) === 'PLN' ? CurrencyRate::NBP : CurrencyRate::ECB;
        }

        return isset(self::PROVIDERS[$id]) ? $id : CurrencyRate::ECB;
    }

    /** @return array<string, string> id => label, automatic providers only */
    public function options(): array
    {
        return [self::AUTOMATIC => __('Automatic: NBP when the home currency is PLN, otherwise ECB')]
            + array_intersect_key(CurrencyRate::providers(), self::PROVIDERS);
    }
}
