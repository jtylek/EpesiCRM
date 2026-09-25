<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Port of Epesi's get_client_ip_address()/get_client_host_name()/parse_user_agent()
 * (include/misc.php), used by App\Http\Middleware\TrackLoginAudit for the same
 * display-only "who/where" fields CRM_LoginAudit recorded — never for feature
 * detection or security decisions, every one of these headers is spoofable.
 */
class ClientInfo
{
    /**
     * Same header-precedence chain as the original: X-Real-IP, then
     * X-Forwarded-For (first hop only), CF-Connecting-IP, Client-IP, falling
     * back to the raw connection address.
     */
    public static function ip(Request $request): string
    {
        $address = $request->server('REMOTE_ADDR', '');

        foreach (['X-Real-IP', 'X-Forwarded-For', 'CF-Connecting-IP', 'Client-IP'] as $header) {
            if ($request->headers->has($header)) {
                $address = $request->headers->get($header);
                break;
            }
        }

        return trim(explode(',', (string) $address)[0]);
    }

    /**
     * Reverse-DNS hostname for a client IP, cached in the session against
     * that IP so a self-heal re-track (see TrackLoginAudit) doesn't repeat
     * the gethostbyaddr() lookup on every request for an unchanged IP.
     */
    public static function hostName(Request $request, string $ip): ?string
    {
        if ($request->session()->get('client_host_name_ip') !== $ip) {
            $request->session()->put('client_host_name_ip', $ip);
            $request->session()->put('client_host_name', gethostbyaddr($ip) ?: null);
        }

        return $request->session()->get('client_host_name');
    }

    /**
     * Best-effort "OS · Browser" label, display-only. Order matters: Edge/
     * Opera/mobile Chrome/mobile Firefox UAs all also contain "Chrome"/
     * "Safari" tokens for compatibility, so their own markers must be
     * checked first, and desktop Safari is only distinguished from Chrome by
     * the "Version/x.y" token Chrome's UA doesn't carry.
     */
    public static function device(Request $request): ?string
    {
        $userAgent = (string) $request->userAgent();
        if ($userAgent === '') {
            return null;
        }

        $os = match (true) {
            (bool) preg_match('/windows/i', $userAgent) => 'Windows',
            (bool) preg_match('/iphone|ipad|ipod/i', $userAgent) => 'iOS',
            (bool) preg_match('/android/i', $userAgent) => 'Android',
            (bool) preg_match('/mac os x|macintosh/i', $userAgent) => 'macOS',
            (bool) preg_match('/linux/i', $userAgent) => 'Linux',
            default => null,
        };

        $browser = match (true) {
            (bool) preg_match('#edg[ai]?/#i', $userAgent) => 'Edge',
            (bool) preg_match('#opr/|opera#i', $userAgent) => 'Opera',
            (bool) preg_match('/crios/i', $userAgent) => 'Chrome',
            (bool) preg_match('/fxios/i', $userAgent) => 'Firefox',
            (bool) preg_match('#chrome/#i', $userAgent) => 'Chrome',
            (bool) preg_match('#firefox/#i', $userAgent) => 'Firefox',
            (bool) preg_match('#version/.*safari#i', $userAgent) => 'Safari',
            default => null,
        };

        $label = trim(($os ?? '').($os && $browser ? ' · ' : '').($browser ?? ''), ' ·');

        return $label !== '' ? mb_substr($label, 0, 64) : null;
    }
}
