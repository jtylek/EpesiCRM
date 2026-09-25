<?php

use App\Support\Setup\BasePath;
use App\Support\Setup\FirstBoot;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// A git checkout has no vendor/ until `composer install` has run (a release
// zip ships it); say so instead of a blank fatal error.
if (! is_file(__DIR__.'/../vendor/autoload.php')) {
    http_response_code(500);
    exit('epesi is missing its vendor/ folder. Run "composer install" in '.dirname(__DIR__).', or unpack a release package that includes it.');
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
