<?php
/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 */
require_once('libs/saja/saja.php');
require_once('include.php');

global $base;
$base = new Epesi();


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
