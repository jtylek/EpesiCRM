<?php
/**
 * Admin class.
 * 
 * This class provides administration module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage admin
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Interface which you must implement if you would like to have module administration entry.
 */
interface Base_AdminModuleCommonInterface {
	public static function admin_access();
	public static function admin_caption();
}

class Base_AdminCommon extends ModuleCommon {
	public static function body_access() {
		return Base_AclCommon::i_am_admin();
	}

	public static function home_page() {
		return array(_M('Administration')=>array(Base_Admin::module_name()));
	}
	
	public static function menu() {
		if(!Base_AclCommon::i_am_admin()) return array();
		return array('__split__'=>array('__weight__'=>2000),_M('Administrator')=>array('__weight__'=>2001));
	}
	
	public static function get_access($module, $section='', $force_check=false) {
		if (!$force_check && Acl::i_am_sa()) return true;
		static $cache = array();
		if (!isset($cache[$module])) {
			$cache[$module] = array();
			$ret = DB::GetAssoc('SELECT section, allow FROM base_admin_access WHERE module=%s', array($module));
			$defaults = array(''=>1);
			if (class_exists($module.'Common') && method_exists($module.'Common', 'admin_access_levels')) {
				$raws = call_user_func(array($module.'Common', 'admin_access_levels'));
				if ($raws==false) {
					$defaults[''] = $raws;
				} else {
					if (is_array($raws))
						foreach ($raws as $s=>$v) {
								$defaults[$s] = $v['default']?? 0;
						}
				}
			}
			foreach($defaults as $s=>$v)
				$cache[$module][$s] = $ret[$s]?? $v;
		}
		return $cache[$module][$section];
	}
}

/**
 * Default abstract class for AdminInterface...
 * You can use it for default admin_access and admin_caption functions.
 * Access: Module administrator
 * Caption: <module_name> module 
 */
abstract class Base_AdminModuleCommon extends ModuleCommon implements Base_AdminModuleCommonInterface {
    public static function admin_access() {
	return Base_AclCommon::i_am_admin();
    }
		
    public static function admin_caption() {
    }
}

if(!interface_exists('Base_AdminInterface', false)) {
/**
 * Interface which you must implement if you would like to have module administration entry.
 * 
 * @package epesi-base-extra
 * @subpackage admin
 */
	interface Base_AdminInterface {
		public function admin();
	}
}

?>