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
	
	public static function user_settings($info = true) {
		if ($info) {  
			if (Base_AclCommon::i_am_user()) return array('Quick access'=>array());
			return array();
		}
		if (!isset(self::$options)) {
			$modules_menu = array();
			foreach(ModuleManager::$modules as $name=>$obj) {
				if ($name=='Base_Admin') continue;
				if ($name=='Base_Menu_QuickAccess') continue;
				if(method_exists($obj['name'].'Common', 'menu')) {
					$module_menu = call_user_func(array($obj['name'].'Common','menu'));
					if(!is_array($module_menu)) continue;
					Base_MenuCommon::add_default_menu($module_menu, $name);
					$modules_menu = array_merge_recursive($modules_menu,$module_menu);
				}
			}
			ksort($modules_menu);
	
			$array = array();
			self::check_for_links($array,'',$modules_menu);
			
			self::$options = $array;
		} else $array = self::$options;
		if (Base_AclCommon::i_am_user()) return array('Quick access'=>$array);
		return array();
	} 

	private function check_for_links(& $result,$prefix,$array){
		foreach($array as $k=>$v){
			if (substr($k,0,2)=='__') continue;
			if (is_array($v) && array_key_exists('__submenu__',$v)) self::check_for_links($result,$prefix.$k.': ',$v);
			elseif(is_array($v)) {
				$http_query = http_build_query($v,'','&');
				$result[] = array('name'=>md5($http_query.'#qa_sep#'.str_replace(' ','_',$prefix.$k))
							,'link'=>$http_query
							,'label'=>$prefix.$k
							,'type'=>'bool'
							,'reload'=>true
							,'default'=>0);
			}
		}
	}

	public static function quick_access_menu() {
		if (!Base_AclCommon::i_am_user()) return array();
		self::user_settings(false);
		$qa_menu = array('__submenu__'=>1);
		foreach (self::$options as $v)
			if (Base_User_SettingsCommon::get('Base_Menu_QuickAccess',$v['name'])) {
				$menu_entry = null;
				parse_str($v['link'],$menu_entry);
				$qa_menu[str_replace('_',' ',$v['label'])] = $menu_entry;
			} 
		if ($qa_menu == array('__submenu__'=>1)) return array();
		return array('Quick Access'=>$qa_menu);
	}
}

?>
