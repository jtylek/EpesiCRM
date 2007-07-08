<?php
/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 */
umask(0);

require_once('include.php');

class Base {
	public $modules;
	public function js(){}
};
global $base;
$base = new Base();


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
