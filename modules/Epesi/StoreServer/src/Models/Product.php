<?php

namespace Epesi\Modules\StoreServer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $table = 'epesi_store_server_products';

    protected $fillable = [
        'module_id',
        'name',
        'description',
        'category',
        'icon',
        'price_amount',
        'price_currency',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Release, $this>
     */
    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    /**
     * @return HasMany<Licence, $this>
     */
    public function licences(): HasMany
    {
        return $this->hasMany(Licence::class);
    }

    public function isFree(): bool
    {
        return ($this->price_amount ?? 0) === 0;
    }

    /**
     * Latest published release. Ordered by id rather than by version string —
     * releases are only ever appended, and comparing arbitrary version strings
     * in SQL is a trap.
     */
    public function latestRelease(): ?Release
    {
        return $this->releases()
            ->where('is_published', true)
            ->orderByDesc('id')
            ->first();
    }
}
