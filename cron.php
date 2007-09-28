<?php
/**
 * This file provides cron functionality... Add it to your cron.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license SPL
 * @version 1.0
 * @package epesi-base
 */
require_once('include.php');

ModuleManager::load_modules();
foreach(ModuleManager::$modules as $name=>$obj) {
	if($name!=$obj['name']) continue;
	if(method_exists($obj['name'].'Common', 'cron')) {
		print($name.":<br>");
		call_user_func(array($obj['name'].'Common','cron'));
		print("<hr>");
	}
}

?>
