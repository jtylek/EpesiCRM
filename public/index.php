<?php

use App\Support\Setup\BasePath;
use App\Support\Setup\FirstBoot;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Windows DevStack's command-line PHP needs its own php.ini (see the Composer
// step below); other setups get the plain commands.
function runsOnDevStack(): bool
{
    return DIRECTORY_SEPARATOR === '\\' && is_dir('C:\\PHPDevStack\\runtime\\config\\generated');
}

/**
 * Show a useful setup page before Laravel/Composer is loaded. This keeps a
 * fresh git checkout from becoming a blank HTTP 500.
 */
function setupNeededPage(string $problem, string $details, array $steps): never
{
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>Epesi setup needed</title>
        <style>
            :root{color-scheme:light;--bg:#f4f7fb;--card:#fff;--ink:#142033;--muted:#5f6f85;--green:#0b9f5a;--green-dark:#087943;--red:#b42318;--red-bg:#fff1f0;--red-line:#f4c7c3;--code:#edf2f7}
            *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}
            .wrap{max-width:820px;margin:0 auto;padding:42px 22px 60px}            h1{font-size:clamp(1.9rem,5vw,2.7rem);line-height:1.15;margin:10px 0 14px}.lead{color:var(--muted);font-size:1.08rem;margin:0 0 24px}
            .card{background:var(--card);border:1px solid #dce4ee;border-radius:14px;padding:26px;box-shadow:0 5px 20px #1f3b5b0d;margin-top:18px}.problem{background:var(--red-bg);border-color:var(--red-line)}
            .problem h2{color:var(--red);margin-top:0}.card h2{font-size:1.25rem;margin:0 0 10px}ol{padding-left:1.35rem;margin-bottom:0}li{margin:10px 0}code,pre{font:14px/1.5 ui-monospace,SFMono-Regular,Consolas,monospace;background:var(--code);border-radius:6px}code{padding:2px 5px}pre{padding:13px 15px;overflow:auto;margin:8px 0 0;white-space:pre-wrap}
            .hint{color:var(--muted);font-size:.94rem;margin-bottom:0}.check{color:var(--green-dark);font-weight:600}
        </style>
    </head>
    <body><main class="wrap">
        <h1>Epesi is almost ready</h1>
        <p class="lead"><?= $escape($details) ?></p>
        <section class="card problem"><h2><?= $escape($problem) ?></h2><p>Complete the steps below, then reload this page.</p></section>
        <section class="card"><h2>What to do</h2><ol>
            <?php foreach ($steps as $step) { ?><li><?= $step ?></li><?php } ?>
        </ol></section>
        <p class="hint">Run the commands from the Epesi directory: <code><?= $escape(dirname(__DIR__)) ?></code>.<?= runsOnDevStack() ? ' Start Apache and MariaDB from the DevStack control panel first.' : '' ?></p>
    </main></body></html>
    <?php
    exit;
}

// Too old a PHP or a missing extension would end in a bare HTTP 500 before the
// setup wizard's server check could run; say what's wrong instead.
require __DIR__.'/../bootstrap/preflight.php';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// A git checkout has no vendor/ until `composer install` has run (a release
// zip ships it); say so instead of a blank fatal error.
if (! is_file(__DIR__.'/../vendor/autoload.php')) {
    setupNeededPage(
        'Composer dependencies are not installed',
        'This checkout does not contain the Composer autoloader, so Laravel cannot start yet.',
        [
            'Open a terminal and go to <code>'.htmlspecialchars(dirname(__DIR__), ENT_QUOTES, 'UTF-8').'</code>.',
            ...(runsOnDevStack() ? [
                '<pre>$env:Path = "C:\\PHPDevStack\\runtime\\php;$env:Path"'."\n".'$env:PHPRC = "C:\\PHPDevStack\\runtime\\config\\generated"'."\n".'composer install</pre>',
                'The second line makes the command-line PHP use the same <code>php.ini</code> as Apache (it enables <code>openssl</code>, <code>curl</code>, <code>zip</code> and the other extensions Composer needs). Without it Composer stops with "The openssl extension is required".',
            ] : [
                '<pre>composer install</pre>',
                'If <code>composer</code> is not recognized, add it to your <code>PATH</code> or run <code>php composer.phar install</code>. On a Linux server run it as the user that owns these files, not as root, so the web server can still write to <code>storage</code> and <code>bootstrap/cache</code>.',
            ]),
            'If Composer reports that <code>openspout</code> supports only PHP 8.4, run <code>composer update openspout/openspout filament/actions --with-all-dependencies</code> once, then repeat <code>composer install</code>.',
            '<span class="check">After Composer finishes, reload this page.</span>',
        ],
    );
}

// A git checkout has no compiled Vite assets until the frontend dependencies
// have been installed and the production bundle has been built.
if (! is_file(__DIR__.'/build/manifest.json')) {
    setupNeededPage(
        'Frontend assets have not been built',
        'Composer is present, but the Vite production bundle is missing. Laravel pages that use @vite would otherwise end in a confusing 500 error.',
        [
            'Open a terminal and go to <code>'.htmlspecialchars(dirname(__DIR__), ENT_QUOTES, 'UTF-8').'</code>.',
            '<pre>npm install'."\n".'npm run build</pre>',
            '<span class="check">After Vite finishes, reload this page.</span>',
        ],
    );
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// A freshly unpacked copy gets its .env and application key here, so the
// setup wizard can open in the browser (see App\Support\Setup\FirstBoot).
FirstBoot::prepare(dirname(__DIR__));

// Served from the folder above public/ (unpacked into a web root, with the
// top-level .htaccess rewriting into public/), or through LARAVEL_BASE_PATH:
// line the script path up with the address so routing finds the base path.
$_SERVER = BasePath::fix($_SERVER);

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
