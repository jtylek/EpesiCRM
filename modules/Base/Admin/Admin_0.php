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
		$mod_ok = array();
		
		$cmr = ModuleManager::call_common_methods('admin_caption');
		foreach($cmr as $name=>$caption) {
			if(!ModuleManager::check_access($name,'admin') || $name=='Base_Admin') continue;
			if(!isset($caption)) $caption = $name.' module';
			$mod_ok[$caption] = $name;
		}
		uksort($mod_ok,'strcasecmp');
		
		$buttons = array();
		foreach($mod_ok as $caption=>$name) {
			if (method_exists($name.'Common','admin_icon')) {
				$icon = call_user_func(array($name.'Common','admin_icon'));
			} else {
				try {
					$icon = Base_ThemeCommon::get_template_file($name,'icon.png');
				} catch(Exception $e) {
					$icon = null;
				}
			}
			$buttons[]= array('link'=>'<a '.$this->create_unique_href(array('href'=>$name)).'>'.$this->ht($caption).'</a>',
						'icon'=>$icon);
		}
		$theme =  & $this->pack_module('Base/Theme');
		$theme->assign('header', $this->t('Modules settings'));
		$theme->assign('buttons', $buttons);
		$theme->display();
	}
	
	public function caption() {
		$module = $this->get_module_variable('href');
		if ($module===null) return 'Administration: Control Panel';
		$func = array($module.'Common','admin_caption');
		if(!is_callable($func)) return 'Administration: '.$module;
		$caption = call_user_func($func);
		if($caption) return 'Administration: '.$caption;
		return 'Administration';
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