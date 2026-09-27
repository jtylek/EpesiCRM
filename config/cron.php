<?php

return [

    /*
     * The secret in the cron URL (Administration → Cron), which runs cron
     * over the web for a host that can only call an address. A file on the
     * server, as Epesi's data/cron_token.php was; "New cron URL" writes a new
     * one. See App\Services\Cron\CronToken.
     */
    'token_path' => env('CRON_TOKEN_PATH', storage_path('app/private/cron-token.txt')),

    /*
     * Administration → Cron warns when cron hasn't run for this many
     * minutes. Some hosts allow cron only every 15 minutes.
     */
    'late_after' => 20,

    /*
     * Cron counts as running on a schedule once it has been called in this
     * many different minutes of the last hour, the first and the last this
     * many minutes apart. A few runs by hand, or the cron URL opened in a
     * browser, don't get there; a cron that runs every minute does ten
     * minutes after it starts.
     */
    'regular_calls' => 3,

    'regular_minutes' => 10,

];
