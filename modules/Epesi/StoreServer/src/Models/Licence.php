<?php

namespace Epesi\Modules\StoreServer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Licence extends Model
{
    protected $table = 'epesi_store_server_licences';

    protected $fillable = [
        'product_id',
        'key',
        'purchaser_name',
        'purchaser_email',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function generateKey(): string
    {
        return implode('-', str_split(strtoupper(Str::random(20)), 5));
    }

    public function isValid(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
