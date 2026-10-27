<?php

return [
    /*
     * An access token this close to its expiry (in seconds) is renewed before
     * it is handed out, so it doesn't run out in the middle of an IMAP session
     * or an API call.
     */
    'refresh_ahead' => (int) env('OAUTH_REFRESH_AHEAD', 300),

    /*
     * `oauth:refresh` (cron, every 15 minutes) renews every token that
     * expires within this many minutes, and so keeps the refresh tokens in
     * use: Microsoft's lapse after 90 days without one.
     */
    'renew_ahead_minutes' => (int) env('OAUTH_RENEW_AHEAD_MINUTES', 20),

    /*
     * How long (in seconds) a refresh waits for another process that is
     * refreshing the same token. Microsoft and Allegro issue a new refresh
     * token on every refresh, so two refreshes at once would lose one.
     */
    'lock_wait' => (int) env('OAUTH_LOCK_WAIT', 15),

    /*
     * The cache store that holds that lock; empty for the default store. It
     * must be one every process sees (web, cron, queue workers): files,
     * memcached, redis or database, never `array`.
     */
    'lock_store' => env('OAUTH_LOCK_STORE'),

    /*
     * Minutes a connect started on a page may take to come back through the
     * callback (signing in at the provider, consenting).
     */
    'flow_lifetime' => (int) env('OAUTH_FLOW_LIFETIME', 15),

    /*
     * Seconds to wait for the provider's token endpoint.
     */
    'timeout' => (int) env('OAUTH_TIMEOUT', 20),
];
