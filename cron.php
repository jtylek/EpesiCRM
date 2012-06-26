<?php
/**
 * This file provides cron functionality... Add it to your cron.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 */
define('CID',false);
define('SET_SESSION',false);
if(isset($argv))
	define('EPESI_DIR','/');
require_once('include.php');

$lock = DATA_DIR.'/cron.lock';
if(file_exists($lock) && filemtime($lock)>time()-6*3600) die();
register_shutdown_function(create_function('','@unlink("'.$lock.'");'));
file_put_contents($lock,'');

set_time_limit(0);
ini_set('memory_limit', '512M');
ModuleManager::load_modules();
Base_AclCommon::set_user(1);
$ret = ModuleManager::call_common_methods('cron');
foreach($ret as $name=>$obj) {
    if(!$obj) continue;
    if(isset($argv))
    	print($name.":\n".strip_tags($obj)."\n\n");
    else
    	print($name.":<br>".$obj."<hr>");
}
@unlink($lock);

?>
