<?php

namespace Epesi\Modules\Currencies\Models;

use Epesi\Modules\Currencies\Services\RateProviders;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings: which provider the daily rates come from, whether cron
 * fetches them, and how far back the first fetch reaches.
 */
class CurrencySetting extends Model
{
    protected $table = 'epesi_currency_settings';

    protected $fillable = ['rate_provider', 'auto_fetch', 'backfill_from', 'last_fetch_at', 'last_fetch_result'];

    protected $attributes = [
        'rate_provider' => RateProviders::AUTOMATIC,
        'auto_fetch' => true,
    ];

    protected function casts(): array
    {
        return [
            'auto_fetch' => 'boolean',
            'backfill_from' => 'date',
            'last_fetch_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'backfill_from' => now()->subYear()->startOfYear()->toDateString(),
        ]);
    }
}
