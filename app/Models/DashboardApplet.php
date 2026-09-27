<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One applet on one user's dashboard: legacy's base_dashboard_applets (tab,
 * col, pos per user_login_id) with its base_dashboard_settings folded into
 * `settings`, and the widget's class in place of a module name. A class that
 * no longer exists (a module moved, disabled or removed) is simply skipped.
 */
class DashboardApplet extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'dashboard_tab_id',
        'widget',
        'col',
        'pos',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'col' => 'integer',
            'pos' => 'integer',
            'settings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<DashboardTab, $this>
     */
    public function tab(): BelongsTo
    {
        return $this->belongsTo(DashboardTab::class, 'dashboard_tab_id');
    }
}
