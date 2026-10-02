<?php

namespace Epesi\Modules\PriorityList\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PriorityListPreference extends Model
{
    protected $table = 'epesi_priority_list_preferences';

    protected $fillable = [
        'user_id',
        'suppress_completion_confirmation',
    ];

    protected function casts(): array
    {
        return [
            'suppress_completion_confirmation' => 'boolean',
        ];
    }

    public static function forUser(User $user): self
    {
        return static::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            ['suppress_completion_confirmation' => false],
        );
    }
}
