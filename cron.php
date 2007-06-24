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


$installed_modules = ModuleManager::get_load_priority_array(true);
if ($installed_modules) {
	foreach($installed_modules as $row) {
		$module = $row['name'];
		$version = $row['version'];
		ModuleManager :: include_init($module, $version);
		ModuleManager :: include_common($module, $version);
		ModuleManager :: create_common_virtual_classes($module, $version);
		ModuleManager :: register($module, $version, $base->modules);
	}
	foreach($base->modules as $name=>$obj) {
		if($name!=$obj['name']) continue;
		if(method_exists($obj['name'].'Common', 'cron')) {
			print($name.":<br>");
			call_user_func(array($obj['name'].'Common','cron'));
			print("<hr>");
		}
	}
}

?>
