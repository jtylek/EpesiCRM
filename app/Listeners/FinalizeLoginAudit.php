<?php

namespace App\Listeners;

use App\Models\LoginAudit;
use Illuminate\Auth\Events\Logout;

/**
 * Stamps the session's login_audits row with the exact logout moment,
 * instead of leaving ended_at at whatever the last page view before logout
 * happened to be (still kept fresh meanwhile by TrackLoginAudit).
 */
class FinalizeLoginAudit
{
    public function handle(Logout $event): void
    {
        self::closeCurrent();
    }

    /**
     * Also used when "Log in as user" hands the session to another account
     * (App\Support\Impersonation), which is a logout without the event.
     */
    public static function closeCurrent(): void
    {
        $auditId = session()->pull('login_audit_id');
        session()->forget('login_audit_user_id');

        if ($auditId) {
            LoginAudit::whereKey($auditId)->update(['ended_at' => now()]);
        }
    }
}
