<?php
/**
 * MenuCommon class.
 *
 * This class provides functionality for MenuCommon class.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com> and Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-base-extra
 * @subpackage menu-quick-access
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_MenuCommon extends ModuleCommon {
	public static function add_default_menu(& $m, $name) {
		foreach($m as $k=>$arr) {
			if(is_array($arr)) {
				if(array_key_exists('__submenu__', $arr))
					self::add_default_menu($m[$k], $name);
				else {
					$action = array();
					if(array_key_exists('__module__',$arr)) {
						$action = array('box_main_module'=>$arr['__module__']);
						unset($arr['__module__']);
					} else
						$action = array('box_main_module'=>$name);
					$action['parent_module'] = $name;
					if(array_key_exists('__function__',$arr)) {
						$action['box_main_function']=$arr['__function__'];
						unset($arr['__function__']);
					}
					if(array_key_exists('__function_arguments__',$arr)) {
						$action['box_main_arguments']=$arr['__function_arguments__'];
						unset($arr['__function_arguments__']);
					}
					if(array_key_exists('__constructor_arguments__',$arr)) {
						$action['box_main_constructor_arguments']=$arr['__constructor_arguments__'];
						unset($arr['__constructor_arguments__']);
					}
					$m[$k] = array_merge($action,$arr);
				}
			} elseif($k!='__icon__' && $k!='__description__' && $k!='__url__' && $k!='__target__' && $k!='__weight__' && $k!='__function__' && $k!='__function_arguments__' && $k!='__module__')
				$m[$k] = null;
		}
	}

	public static function get_menus() {
		static $menus;
		if(!isset($menus)) { //here can be bug with caching and location
			$menus = ModuleManager::call_common_methods('menu');
			foreach($menus as $m=>$r)
				if(!is_array($r)) unset($menus[$m]);
		}
		return $menus;
	}

        private static $quick = array();
	public static function add_quick_menu($name,$action) {
		self::$quick[$name] = $action;
	}

	public static function get_quick_menu() {
		return self::$quick;
	}

	public static function clean_quick_menu() {
		self::$quick = array();
	}
}
on_exit(array('Base_MenuCommon','clean_quick_menu'));
?>
