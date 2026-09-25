<?php

namespace App\Http\Middleware;

use App\Models\LoginAudit;
use App\Support\ClientInfo;
use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Port of Epesi's CRM_LoginAuditCommon::init()/update() (on_init/shutdown
 * hooks in the old module) as a middleware: on the first authenticated
 * request of a session, opens a login_audits row; on every later one,
 * bumps its ended_at so it always reflects "still active as of last request".
 *
 * Mirrors the original's self-heal too — the session (independent of the
 * database) can outlive its login_audits row (e.g. a DB reset while the
 * session is still alive), so a session pointing at a missing row is
 * treated as untracked instead of silently updating nothing forever.
 *
 * `login` is a snapshot of the email at tracking time, not a live lookup —
 * `user_id` is nullOnDelete, so once an account is removed a row would
 * otherwise become anonymous. Old Epesi never had this problem since
 * base_login_audit.user_login_id was a plain int, never nulled.
 */
class TrackLoginAudit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (Auth::check()) {
            $this->track($request);
        }

        return $response;
    }

    private function track(Request $request): void
    {
        $user = Auth::user();
        $auditId = $request->session()->get('login_audit_id');
        $tracked = $auditId
            && $request->session()->get('login_audit_user_id') === $user->id
            && LoginAudit::whereKey($auditId)->exists();

        if ($tracked) {
            LoginAudit::whereKey($auditId)->update(['ended_at' => now()]);

            return;
        }

        $ip = ClientInfo::ip($request);

        $audit = LoginAudit::create([
            'user_id' => $user->id,
            'login' => $user->email,
            'started_at' => now(),
            'ended_at' => now(),
            'ip_address' => $ip,
            'host_name' => ClientInfo::hostName($request, $ip),
            'device' => ClientInfo::device($request),
            // Only when set: the administrator signing in to run the database
            // update that adds this column must not hit its absence first.
            ...array_filter(['impersonated_by' => Impersonation::impersonatorId()]),
        ]);

        $request->session()->put('login_audit_id', $audit->id);
        $request->session()->put('login_audit_user_id', $user->id);
    }
}
