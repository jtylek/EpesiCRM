<?php
/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence SPL
 */
define('_VALID_ACCESS',true);

umask(0);

$delimiter = ($_ENV['OS']=='Windows_NT')?';':':';
ini_set('include_path','libs'.$delimiter.ini_get('include_path'));



/**
 * Include database configuration file.
 */
require_once "data/config.php";

//include all other necessary files
$include_dir = "include/";
$to_include = scandir($include_dir);
foreach ($to_include as $entry)
	// Include all base files.
	if (ereg('.\.php$', $entry))
		require_once ($include_dir . $entry);

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
