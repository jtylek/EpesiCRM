<?php

namespace Epesi\Modules\Currencies\Filament\Widgets;

use App\Filament\Dashboard\Applet;
use App\Filament\Dashboard\IsApplet;
use Epesi\Modules\Currencies\Services\CurrencyRepository;
use Epesi\Modules\Currencies\Services\RateFormatter;
use Epesi\Modules\Currencies\Services\RateResolver;
use Epesi\Modules\Currencies\Services\ResolvedRate;
use Filament\Forms\Components\Select;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * The Currency Converter applet: the amount typed on the left shows on the
 * right in another currency, at the rate RateResolver gives a document dated
 * on the chosen day. The result is worked in the browser; only a change of
 * currency or date asks the server for a rate.
 * Exchange Rates' Converter button opens the same component in a modal.
 */
class CurrencyConverterWidget extends Widget implements Applet
{
    use IsApplet;

    protected string $view = 'epesi-currencies::converter';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 8;

    /** In Exchange Rates' modal rather than on the Dashboard: no section of its own. */
    public bool $inModal = false;

    public string $from = '';

    public string $to = '';

    public string $date = '';

    /** The resolved rate for the browser: from × rate = to; null when there is none. */
    public ?float $rate = null;

    public int $decimals = 2;

    /** "NBP, 2026-10-02": where the rate came from. */
    public string $source = '';

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole(['super_admin', 'manager', 'employee']) ?? false;
    }

    public static function getAppletCaption(): string
    {
        return __('Currency Converter');
    }

    public static function getAppletDescription(): ?string
    {
        return __('Converts an amount at the exchange rate of a given day');
    }

    public static function getAppletSettingsSchema(): array
    {
        $currencies = fn (): array => app(CurrencyRepository::class)->options();

        return [
            Select::make('from')->label('From')->options($currencies)->placeholder(__('Automatic')),
            Select::make('to')->label('To')->options($currencies)->placeholder(__('Home currency')),
        ];
    }

    public static function getAppletSettingsDefaults(): array
    {
        return ['from' => null, 'to' => null];
    }

    public function mount(): void
    {
        $currencies = app(CurrencyRepository::class);
        $active = array_keys($currencies->options());

        $this->to = $this->pick($this->appletSetting('to'), $active) ?? $currencies->home();
        $this->from = $this->pick($this->appletSetting('from'), $active)
            ?? collect($active)->first(fn (string $code): bool => $code !== $this->to)
            ?? $this->to;
        $this->date = today()->toDateString();

        $this->resolve();
    }

    /** @return array<string, string> */
    public function currencies(): array
    {
        return app(CurrencyRepository::class)->options();
    }

    public function swap(): void
    {
        [$this->from, $this->to] = [$this->to, $this->from];

        $this->resolve();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'to', 'date'], true)) {
            $this->resolve();
        }
    }

    protected function resolve(): void
    {
        $currencies = app(CurrencyRepository::class);
        $active = array_keys($currencies->options());

        // Whatever came back from the browser: an active currency and a real date, or the defaults.
        $this->from = $this->pick($this->from, $active) ?? $currencies->home();
        $this->to = $this->pick($this->to, $active) ?? $currencies->home();
        $this->date = rescue(fn (): string => Carbon::parse($this->date)->toDateString(), today()->toDateString(), report: false);

        try {
            $rate = app(RateResolver::class)->resolve($this->from, $this->to, $this->date);
        } catch (Throwable) {
            $rate = new ResolvedRate(null, ResolvedRate::NONE);
        }

        $this->rate = $rate->rate;
        $this->decimals = $currencies->decimals($this->to);
        $this->source = match (true) {
            ! $rate->found() => '',
            $rate->source === ResolvedRate::SAME => __('same currency'),
            $rate->source === ResolvedRate::CUSTOM => __('custom rate').($rate->rateDate ? ', '.$rate->rateDate->toDateString() : ''),
            default => strtoupper($rate->source).($rate->rateDate ? ', '.$rate->rateDate->toDateString() : ''),
        };
    }

    public function formattedRate(): string
    {
        return RateFormatter::format($this->rate);
    }

    /** @param  array<int, string>  $active */
    protected function pick(mixed $code, array $active): ?string
    {
        $code = is_string($code) ? strtoupper($code) : null;

        return $code !== null && in_array($code, $active, true) ? $code : null;
    }
}
