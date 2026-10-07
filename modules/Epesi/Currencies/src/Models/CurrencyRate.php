<?php

namespace Epesi\Modules\Currencies\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One published rate: 1 `base` = `rate` `quote` on `rate_date`, as the
 * provider published it. ECB rows are EUR -> X, NBP rows X -> PLN, custom rows
 * whatever pair an administrator typed.
 */
class CurrencyRate extends Model
{
    public const ECB = 'ecb';

    public const NBP = 'nbp';

    public const CUSTOM = 'custom';

    protected $fillable = ['provider', 'base', 'quote', 'rate_date', 'rate', 'fetched_at'];

    protected function casts(): array
    {
        return [
            'rate_date' => 'date',
            'rate' => 'float',
            'fetched_at' => 'datetime',
        ];
    }

    /** @return array<string, string> provider => label */
    public static function providers(): array
    {
        return [
            self::ECB => __('ECB (European Central Bank)'),
            self::NBP => __('NBP (National Bank of Poland)'),
            self::CUSTOM => __('Custom'),
        ];
    }
}
