<?php

namespace Epesi\Modules\Watchdog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One user watching every record of a type ("task") — a
 * `utils_watchdog_category_subscription` row.
 */
class CategorySubscription extends Model
{
    protected $table = 'epesi_watchdog_category_subscriptions';

    protected $fillable = [
        'user_id',
        'category',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
