<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage setup
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_SetupCommon extends ModuleCommon {
	public static function body_access() {
		return (Variable::get('anonymous_setup') || Acl::check('Administration','Main'));
	}

	public static function admin_access() {
		return (Variable::get('anonymous_setup') || Acl::check('Administration','Main'));
	}
	
	public static function admin_caption() {
		return 'Main setup';
	}
	
	public static function refresh_available_modules() {
		$module_dirs = ModuleManager::list_modules();
		DB::Execute('TRUNCATE TABLE available_modules');
		foreach($module_dirs as $name => $v)
			foreach($v as $ver => $u) 
				DB::Execute('INSERT INTO available_modules VALUES(%s, %d, %s)',array($name,$ver,$u));
		return $module_dirs;
	}
}
?>
