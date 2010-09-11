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
set_time_limit(0);

$user = Acl::get_user();
Acl::set_user();

ModuleManager::load_modules();
$ret = ModuleManager::call_common_methods('cron');
foreach($ret as $name=>$obj) {
	print($name.":<br>".$obj."<hr>");
}

Acl::set_user($user);

?>
