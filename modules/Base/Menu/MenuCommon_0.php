<?php
/**
 * MenuCommon class.
 *
 * This class provides functionality for MenuCommon class.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com> and Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage menu
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
		static $user;
		if(!isset($menus) || $user!=Acl::get_user()) {
			$user = Acl::get_user();
			$menus = ModuleManager::call_common_methods('menu',false);
			foreach($menus as $m=>$r)
				if(!is_array($r)) unset($menus[$m]);
		}
		return $menus;
	}
	
	public static function create_href_js($mod,$arr,$ret='js') {
		$main_mod = $arr['box_main_module'];
		unset($arr['box_main_module']);
		if(isset($arr['box_main_function'])) {
			$main_func = $arr['box_main_function'];
			unset($arr['box_main_function']);
		} else {
			$main_func = null;
		}
		if(isset($arr['box_main_arguments'])) {
			$main_args = $arr['box_main_arguments'];
			unset($arr['box_main_arguments']);
		} else {
			$main_args = null;
		}
		if(isset($arr['box_main_constructor_arguments'])) {
			$constr_args = $arr['box_main_constructor_arguments'];
			unset($arr['box_main_constructor_arguments']);
		} else {
			$constr_args = null;
		}
		switch($ret) {
			case 'js':
				return $mod->create_main_href_js($main_mod,$main_func,$main_args,$constr_args,$arr);
			case 'href':
				return $mod->create_main_href($main_mod,$main_func,$main_args,$constr_args,$arr);
			case 'array':
				return array_merge($arr,Base_BoxCommon::create_href_array($mod,$main_mod,$main_func,$main_args,$constr_args));
		}
		return '';
	}
	
	public static function create_href($mod,$arr) {
		return self::create_href_js($mod,$arr,'href');
	}

	public static function create_array($arr) {
		return self::create_href_js(null,$arr,'array');
	}
}

?>
