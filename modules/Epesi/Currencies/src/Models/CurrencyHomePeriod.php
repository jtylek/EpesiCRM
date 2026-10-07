<?php

namespace Epesi\Modules\Currencies\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * From `effective_from` on, `currency_code` is the home (functional) currency,
 * until the next period starts. See CurrencyRepository::home().
 */
class CurrencyHomePeriod extends Model
{
    protected $fillable = ['currency_code', 'effective_from'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
        ];
    }
}
