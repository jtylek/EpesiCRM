<?php
/**
 * This file provides monitoring functionality...
 * Returns time of execution in ms
 * Available arguments: number (if defined and true script will return 999999 on any error), type (session, database, data_directory - run only one test)
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
try {
    require_once('include.php');

    ModuleManager::load_modules();
    Base_AclCommon::set_sa_user();
} catch(Exception $e) {
    if(isset($_GET['number']) && $_GET['number']) die('999999');
    die('error: init');
}

class MonitoringErrorObserver extends ErrorObserver {
    public function update_observer($type, $message,$errfile,$errline,$errcontext, $backtrace) {
        if(isset($_GET['number']) && $_GET['number']) die('999999');
        die('error: '.$type.'# '.$message);
    }
}

$err = new MonitoringErrorObserver();
ErrorHandler::add_observer($err);


function test_database() {
    $up = epesi_requires_update();
    if($up===null) {
        if(isset($_GET['number']) && $_GET['number']) die('999999');
        die('error: database');
    } elseif($up===true) {
        if(isset($_GET['number']) && $_GET['number']) die('999999');
        die('error: version');
    }
}


function test_session() {
    $tag = microtime(1);
    $session_id = 'monitoring_'.md5(DATABASE_NAME.'#'.DATABASE_HOST.'#'.DATABASE_DRIVER);
    $_SESSION = EpesiSession::get($session_id);
    $_SESSION['monitoring'] = $tag;
    $_SESSION['client']['monitoring'] = $tag;
    EpesiSession::set($session_id, $_SESSION);
    $_SESSION = EpesiSession::get($session_id);
    if(!isset($_SESSION['monitoring']) || !isset($_SESSION['client']['monitoring']) || $_SESSION['monitoring'] != $tag || $_SESSION['client']['monitoring'] != $tag) {
    	EpesiSession::set($session_id, $_SESSION);
        if(isset($_GET['number']) && $_GET['number']) die('999999');
        die('error: session');
    }
    EpesiSession::set($session_id, $_SESSION);
}

function test_data_directory() {
    $tag = (string)microtime(1);
    if(!is_writable(DATA_DIR)) {
        if(isset($_GET['number']) && $_GET['number']) die('999999');
        die('error: data directory now writable');
    }
    $test_file = DATA_DIR.'/monitoring_test_file.txt';
    @file_put_contents($test_file, $tag);
    if(@file_get_contents($test_file) != $tag) {
        if(isset($_GET['number']) && $_GET['number']) die('999999');
        die('error: data directory write/read error');
    }
    unlink($test_file);
}

$t = microtime(1);
if(isset($_GET['type'])) {
    if(in_array($_GET['type'],array('database','session','data_directory'))) {
        call_user_func('test_'.$_GET['type']);
    } else {
        die('Invalid test type: '.$_GET['type']);
    }
} else {
    test_database();
    test_session();
    test_data_directory();
}

die(round((microtime(1)-$t)*1000));
