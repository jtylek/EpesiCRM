<?php
/**
 * This file provides cron functionality... Add it to your cron.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 */
define('CID',1);
define('SET_SESSION',false);
if (php_sapi_name() == 'cli') {
    define('EPESI_DIR', '/');
    if (isset($argv[1])) {
        define('DATA_DIR', $argv[1]);
    }
} elseif (!isset($_GET['token'])) {
    die('Missing token in URL - please go to Administrator Panel->Cron and copy valid cron URL.');
} else {
    defined("_VALID_ACCESS") || define("_VALID_ACCESS", true);
    require_once('include/include_path.php');
    require_once('include/data_dir.php');
    if(!file_exists(DATA_DIR.'/cron_token.php'))
        die('Invalid token in URL - please go to Administrator Panel->Cron and copy valid cron URL.');
    require_once(DATA_DIR.'/cron_token.php');
    if(CRON_TOKEN!=$_GET['token'])
        die('Invalid token in URL - please go to Administrator Panel->Cron and copy valid cron URL.');
}
require_once('include.php');

set_time_limit(0);
ini_set('memory_limit', '512M');
ModuleManager::load_modules();
Base_AclCommon::set_sa_user();

$up = epesi_requires_update();
if($up===null) {
    die('error: database');
} elseif($up===true) {
    die('error: version');
}

DBSession::open('','');
DBSession::read('monitoring');
$t = microtime(1);
$_SESSION['monitoring'] = $t;
$_SESSION['client']['monitoring'] = $t;
DBSession::write('monitoring','');
$_SESSION = array();
DBSession::read('monitoring');
if(!isset($_SESSION['monitoring']) || !isset($_SESSION['client']['monitoring']) || $_SESSION['monitoring'] != $t || $_SESSION['client']['monitoring'] != $t) {
    die('error: session');
}

if(!is_writable(DATA_DIR)) {
    die('error: data directory now writable');
}

die('ok');
