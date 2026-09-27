<?php

/*
 * epesi's cron. Have the server run it every minute, e.g. with this crontab
 * line (Administration → Cron shows it with this installation's paths):
 *
 *     * * * * * php /path/to/epesi/cron.php > /dev/null 2>&1
 *
 * It runs every scheduled task that is due, in this one PHP process, from
 * whichever folder cron starts in: `php artisan epesi:cron` does the same.
 * A host that can only call a web address uses the cron URL shown under
 * Administration → Cron instead.
 */

use Illuminate\Foundation\Application;
use Symfony\Component\Console\Input\ArgvInput;

// A web server showing this folder instead of public/ must not run it.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('LARAVEL_START', microtime(true));

require __DIR__.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

exit($app->handleCommand(new ArgvInput([$argv[0], 'epesi:cron', ...array_slice($argv, 1)])));
