<?php
/**
 * QuickAccess class.
 * 
 * This class provides functionality for QuickAccess class. 
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-base-extra
 * @subpackage menu-quick-access
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Menu_QuickAccessCommon extends ModuleCommon {
	private static $options = null;
	
	public static function user_settings_icon() {
		return Base_ThemeCommon::get_template_file('Base_Menu_QuickAccess','icon.png');
	}
	
	public static function user_settings() {
		self::get_options();
		if (Acl::is_user()) return array('Quick access menu'=>self::$options);
		return array();
	} 
	
	public static function get_options() {
		if (isset(self::$options)) return self::$options;
		self::$options = array();
		$modules_menu = array();
		
		$menus = Base_MenuCommon::get_menus();
		ksort($menus);
		foreach($menus as $name=>$ret) {
			if ($name=='Base_Admin') continue;
			if ($name=='Base_Menu_QuickAccess') continue;
			Base_MenuCommon::add_default_menu($ret, $name);
			$modules_menu = array_merge($modules_menu,self::check_for_links('',$ret,$name));
		}
		//print_r($modules_menu);
		self::$options = & $modules_menu;
		return self::$options;
	}

	private static function check_for_links($prefix,$array,$mod){
		$result = array();
		foreach($array as $k=>$v){
			if (substr($k,0,2)=='__') continue;
			if (is_array($v) && array_key_exists('__submenu__',$v)) $result = array_merge($result,self::check_for_links($prefix.$k.': ',$v,$mod));
			elseif(is_array($v)) {
				$http_query = http_build_query($v,'','&');
				$result[] = array('name'=>md5($http_query.'#qa_sep#'.$prefix.$k)
							,'link'=>$http_query
							,'label'=>$prefix.$k
							,'type'=>'bool'
							,'reload'=>true
							,'default'=>0
							,'module'=>$mod);
			}
		}
		return $result;
	}

	public static function quick_access_menu() {
		if (!Base_AclCommon::i_am_user()) return array();
		self::get_options();
		$qa_menu = array('__submenu__'=>1);
		foreach (self::$options as $v)
			if (Base_User_SettingsCommon::get('Base_Menu_QuickAccess',$v['name'])) {
				$menu_entry = null;
				parse_str($v['link'],$menu_entry);
				$qa_menu[$v['label']] = $menu_entry;
			} 
		if ($qa_menu == array('__submenu__'=>1)) return array();
		return array('Quick Access'=>$qa_menu);
	}
}

?>
