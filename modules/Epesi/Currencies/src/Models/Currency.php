<?php

namespace Epesi\Modules\Currencies\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A currency this install uses, by ISO 4217 code. Other tables store the code,
 * not this row's id.
 */
class Currency extends Model
{
    protected $fillable = ['code', 'name', 'decimals', 'active', 'position'];

    protected $attributes = [
        'decimals' => 2,
        'active' => true,
        'position' => 0,
    ];

    protected function casts(): array
    {
        return [
            'decimals' => 'integer',
            'active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /** @return array<string, array{0: string, 1: int}> ISO code => [English name, minor units] */
    public static function iso(): array
    {
        static $list = null;

        return $list ??= require __DIR__.'/../../resources/iso4217.php';
    }
}
