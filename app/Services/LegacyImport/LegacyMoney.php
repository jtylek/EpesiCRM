<?php

namespace App\Services\LegacyImport;

use Illuminate\Support\Facades\DB;

/**
 * A legacy currency field's raw value, "60__2": the amount, then the id of the
 * currency in the legacy `utils_currency` table, which becomes its ISO code.
 */
final class LegacyMoney
{
    /** @var array<int, string>|null legacy currency id => code */
    private static ?array $codes = null;

    private static string $default = 'USD';

    /** @return array{0: ?string, 1: ?string} amount (as a decimal string) and currency code, both null for an empty value */
    public static function parse(?string $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [null, null];
        }

        [$amount, $currency] = array_pad(explode('__', $raw, 2), 2, null);

        if (! is_numeric($amount)) {
            return [null, null];
        }

        return [(string) $amount, self::code($currency === null ? null : (int) $currency)];
    }

    /** The legacy install's default currency, for an amount that names none. */
    public static function defaultCode(): string
    {
        self::load();

        return self::$default;
    }

    public static function code(?int $legacyId): string
    {
        self::load();

        return self::$codes[$legacyId] ?? self::$default;
    }

    private static function load(): void
    {
        if (self::$codes !== null) {
            return;
        }

        $legacy = DB::connection('legacy');
        self::$codes = [];

        if (! $legacy->getSchemaBuilder()->hasTable('utils_currency')) {
            return;
        }

        foreach ($legacy->table('utils_currency')->get(['id', 'code', 'default_currency']) as $currency) {
            self::$codes[(int) $currency->id] = strtoupper((string) $currency->code);

            if ((int) $currency->default_currency === 1) {
                self::$default = strtoupper((string) $currency->code);
            }
        }
    }
}
