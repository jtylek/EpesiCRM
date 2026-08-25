<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license MIT
 * @package epesi-base
 */

if (__FILE__ == realpath($_SERVER['SCRIPT_FILENAME'])) die("Direct access forbidden");

defined("_VALID_ACCESS") || define("_VALID_ACCESS", true);

// Mirrors the security headers/memory_limit from the root .htaccess template (htaccess.txt) so
// they still apply on hosts where that file gets rejected (see AI-shared/MIGRATION_NOTES.md §55)
// or where mod_php isn't in use (php_value there is silently ignored under PHP-FPM/CGI).
if (PHP_SAPI !== 'cli') {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
}
$memory_limit = ini_get('memory_limit');
if ($memory_limit !== '-1' && $memory_limit !== '') {
    $unit = strtolower(substr($memory_limit, -1));
    $memory_limit_bytes = (int) $memory_limit * ($unit === 'g' ? 1073741824 : ($unit === 'm' ? 1048576 : ($unit === 'k' ? 1024 : 1)));
    if ($memory_limit_bytes < 256 * 1024 * 1024) ini_set('memory_limit', '256M');
}

umask(0022);

chdir(dirname(__FILE__));
try {
    require_once('vendor/autoload.php');
    require_once('include/include_path.php');
    require_once('include/data_dir.php');
    // Entry points that pull in the full app this way (ajax/refresh-style
    // module scripts like Base_Notify/refresh.php) don't check for an
    // installed app themselves the way index.php does before ever reaching
    // here - without this, a request arriving between wiping data/config.php
    // and finishing setup again (e.g. a stale tab's background poller)
    // fatals raw instead of just quietly doing nothing.
    if (!file_exists(DATA_DIR.'/config.php')) exit();
    require_once('include/config.php');
    require_once('include/maintenance_mode.php');
    require_once('include/epesi.php');
    require_once('include/error.php');
    require_once('include/magicquotes.php');
    require_once('include/database.php');
    require_once('include/cache.php');
    require_once('include/misc.php');
    require_once('include/module_primitive.php');
    require_once('include/module_install.php');
    require_once('include/module_common.php');
    require_once('include/module.php');
    require_once('include/module_manager.php');
    require_once('include/autoloader.php');
    require_once('include/session.php');
    require_once('include/variables.php');
    require_once('include/history.php');
    require_once('include/patches.php');
    require_once('include/simple_login.php');
} catch (Exception $e) {
    die($e->getMessage());
}
