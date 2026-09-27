<?php

namespace Epesi\Modules\PriorityList\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings for this install: which record types an administrator
 * has turned on or off, alias => bool, overriding the default every
 * candidate type starts with (see PriorityList::isEnabled()). A table rather
 * than config/, because it is entered through the GUI by an operator.
 */
class PriorityListSetting extends Model
{
    protected $table = 'epesi_priority_list_settings';

    protected $fillable = [
        'overrides',
    ];

    protected function casts(): array
    {
        return [
            'overrides' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], []);
    }
}
