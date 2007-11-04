<?php
/**
 * Admin class.
 * 
 * This class provides administration module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-base-extra
 * @subpackage admin
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides administration menu. Just place admin(), admin_caption() (and admin_access()) 
 * functions inside your module.
 * You can extend AdminModule for default access privileges.
 */
class Base_Admin extends Module {
		
	public function body() {
		$module = $this->get_module_variable_or_unique_href_variable('href');

		if(isset($_REQUEST['admin_href'])) {
			$module = $_REQUEST['admin_href'];
			$this->set_module_variable('href', $module);
		}

		if($module) {
			$this->pack_module($module,null,'admin');
		} else {
			$this->list_admin_modules();
		}
		
	} 
		
	public function reset() {
		$this->unset_module_variable('href');
		location(array());
	}
	
	private function list_admin_modules() {
		$lang = & $this->init_module('Base/Lang');
		
		$mod_ok = array();
		
		$cmr = ModuleManager::call_common_methods('admin_caption');
		foreach($cmr as $name=>$caption) {
			if(!ModuleManager::check_access($name,'admin') || $name=='Base_Admin') continue;
			if(!isset($caption)) $caption = $name.' module';
			$mod_ok[$caption] = $name;
		}
		ksort($mod_ok);
		
		$buttons = array();
		foreach($mod_ok as $caption=>$name) {
			if (method_exists($name.'Common','admin_icon')) {
				$icon = call_user_func(array($name.'Common','admin_icon'));
			} else 
				$icon = Base_ThemeCommon::get_template_file($name,'icon.png');
			$buttons[]= array('link'=>'<a '.$this->create_unique_href(array('href'=>$name)).'>'.$lang->t($caption).'</a>',
						'icon'=>$icon);
		}
		$theme =  & $this->pack_module('Base/Theme');
		$theme->assign('header', $lang->t('Modules settings'));
		$theme->assign('buttons', $buttons);
		$theme->display();
	}
	
	public static function admin_menu() {
		if(!Base_AclCommon::i_am_admin()) return array();
		
		$mod_cpy = ModuleManager::call_common_methods('admin_caption');
		foreach($mod_cpy as $name=>$caption) {
			if(!ModuleManager::check_access($name,'admin') || $name=='Base_Admin') continue;
			if(!isset($caption)) $caption = $name.' module';
			$mod_ok[$caption] = array('admin_href'=>$name);
		}
		$mod_ok['__submenu__']=1;
		
		ksort($mod_ok);
		//return array();
		return array('Administrator'=>array_merge(array('Control panel'=>array(), '__split__'=>1), $mod_ok));
	}
	
	public function caption() {
		$module = $this->get_module_variable('href');
		$func = array($module.'Common','admin_caption');
		if(!is_callable($func)) return;
		$caption = call_user_func($func);
		if($caption) return "Administration: ".$caption;
		return "Administration";
	}
}

if(!interface_exists('Base_AdminInterface')) {
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