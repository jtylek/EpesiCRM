<?php

namespace Epesi\Modules\PriorityList\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One record on one user's priority list. `position` is its place on the
 * list, 1 first.
 */
class Entry extends Model
{
    protected $table = 'epesi_priority_list_entries';

    protected $fillable = [
        'user_id',
        'record_type',
        'record_id',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function record(): MorphTo
    {
        return $this->morphTo();
    }
}
