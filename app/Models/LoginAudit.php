<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Port of Epesi's CRM_LoginAudit ("base_login_audit" table): one row per
 * login session, self-healing and kept alive by App\Http\Middleware\TrackLoginAudit
 * the same way CRM_LoginAuditCommon::init()/update() did — see that class for
 * the original session-tracking logic this mirrors.
 */
class LoginAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'login',
        'impersonated_by',
        'started_at',
        'ended_at',
        'ip_address',
        'host_name',
        'device',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
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
     * The super_admin who opened this session with "Log in as user".
     *
     * @return BelongsTo<User, $this>
     */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_by');
    }
}
