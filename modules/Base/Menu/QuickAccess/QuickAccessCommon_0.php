<?php
/**
 * QuickAccess class.
 *
 * This class provides functionality for QuickAccess class.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage menu-quickaccess
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Menu_QuickAccessCommon extends ModuleCommon {
	// AdminLTE-only: Base_AdminlteIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/adminlte_icons.php.
	public static function adminlte_icon() { return 'bi-star'; }

	private static $options = null;

	public static function user_settings() {
		self::get_options();
		$ret_opts = array();
		foreach(self::$options as $opt) {
			unset($opt['link']);
			$name = $opt['name'];
			unset($opt['name']);
			$opt = array_merge($opt,array(
						'type'=>'bool',
						'reload'=>true,
						'default'=>1
						));
			// Default on for both columns, except the Dashboard module's own
			// entry: showing a "Dashboard" widget on the Dashboard itself is
			// circular/pointless, so only its Launchpad default stays on.
			$dashboard_default = ($opt['module']==Base_Dashboard::module_name())?0:1;
			// Both elems share the group's own 'label' (the module item's name,
			// used as the row header) - each elem's own 'values' is its column
			// caption, shown inline by non-adminlte themes and used to build the
			// "Dashboard"/"Launchpad" column headers in the adminlte theme (see
			// Base_User_Settings::body()).
			$ret_opts[] = array('type'=>'group', 'label'=>$opt['label'], 'elems'=>array(
						array_merge($opt,array(
							'values'=>__('Dashboard'),
							'default'=>$dashboard_default,
							'name'=>$name.'_d')),
						array_merge($opt,array(
							'values'=>__('Launchpad'),
							'name'=>$name.'_l'))
					));
		}
		//trigger_error(print_r($ret_opts,true));
		if (Acl::is_user()) return array(__('Quick Access')=>$ret_opts);
		return array();
	}

	public static function get_options() {
		static $user;
		if (isset(self::$options) && $user==Acl::get_user()) return self::$options;
		$user = Acl::get_user();
		self::$options = array();
		$modules_menu = array();

		$menus = Base_MenuCommon::get_menus();
		//ksort($menus);
		foreach($menus as $name=>$ret) {
			if ($name=='Base_Admin') continue;
			if ($name==Base_Menu_QuickAccessCommon::module_name()) continue;
			Base_MenuCommon::add_default_menu($ret, $name);
			$modules_menu = array_merge($modules_menu,self::check_for_links('',$ret,$name));
		}
		usort($modules_menu,fn($a, $b) => strcmp($a['label'],$b['label']));
		self::$options = & $modules_menu;
		return self::$options;
	}

	private static function check_for_links($prefix,$array,$mod,$prefixt=''){
		$result = array();
		foreach($array as $k=>$v){
			if (str_starts_with($k, '__')) continue;
			$c_pre = $prefixt._V($k); // ****** Menu options label
			if (is_array($v) && array_key_exists('__submenu__',$v)) $result = array_merge($result,self::check_for_links($prefix.$k.': ',$v,$mod,$c_pre.': '));
			elseif(is_array($v)) {
				$result[] = array('name'=>md5($prefix.$k)
							,'link'=>$v
							,'label'=>$c_pre
							,'module'=>$mod);
			}
		}
		return $result;
	}
}

?>
