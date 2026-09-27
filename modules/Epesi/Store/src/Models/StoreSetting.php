<?php

namespace Epesi\Modules\Store\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings for this install's store connection: the licence key for
 * paid modules. A table rather than config/, because it is entered through the
 * GUI by an operator — a module can't edit the app's config files, and
 * shouldn't try. The store's address itself is StoreClient::API_URL.
 */
class StoreSetting extends Model
{
    protected $table = 'epesi_store_settings';

    protected $fillable = [
        'licence_key',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], []);
    }
}
