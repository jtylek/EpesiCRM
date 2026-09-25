<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where one applet sits on one user's dashboard: legacy's
 * base_dashboard_applets (col/pos per user_login_id), keyed by the widget's
 * class rather than a module name. A class that no longer exists (a module
 * moved or removed) is simply skipped, and that widget falls back to a
 * default place.
 */
class DashboardApplet extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'widget',
        'col',
        'pos',
    ];

    protected function casts(): array
    {
        return [
            'col' => 'integer',
            'pos' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
