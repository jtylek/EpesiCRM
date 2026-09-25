<?php

namespace Epesi\Modules\Store\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings for this install's store connection. A table rather than
 * config/, because these are entered through the GUI by an operator — a module
 * can't edit the app's config files, and shouldn't try.
 */
class StoreSetting extends Model
{
    protected $table = 'epesi_store_settings';

    protected $fillable = [
        'catalog_url',
        'licence_key',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], []);
    }

    public function isConfigured(): bool
    {
        return filled($this->catalog_url);
    }
}
