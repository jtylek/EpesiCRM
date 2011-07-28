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
			else $caption = $this->t($caption);
			$mod_ok[$caption] = $name;
		}
		uksort($mod_ok,'strcasecmp');
		if (Base_AclCommon::i_am_sa())
			$mod_ok['Admin Panel Access'] = 'Base_Admin';
		
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
			$buttons[]= array('link'=>'<a '.$this->create_unique_href(array('href'=>$name)).'>'.$caption.'</a>',
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
	
	public function admin() {
		if(!Base_AclCommon::i_am_sa() || $this->is_back()) {
			$this->parent->reset();
			return;
		}
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
		
		$cmr = ModuleManager::call_common_methods('admin_caption');
		foreach($cmr as $name=>$caption) {
			if(!ModuleManager::check_access($name,'admin') || $name=='Base_Admin') continue;
			if(!isset($caption)) $caption = $name.' module';
			else $caption = $this->t($caption);
			$mod_ok[$caption] = $name;
		}
		uksort($mod_ok,'strcasecmp');
		
		$form = $this->init_module('Libs_QuickForm');
		
		$buttons = array();
		load_js('modules/Base/Admin/js/main.js');
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
			$button_id = $name.'__button';
			$enable_field = $name.'_enable';
			$sections = array();
			$sections_id = $name.'__sections';

			$enable_default = Base_AdminCommon::get_access($name);
			$form->addElement('checkbox', $enable_field, $this->t($enable_default===null?'Access blocked':'Allow access'), null, array('onchange'=>'admin_switch_button("'.$button_id.'",this.checked, "'.$sections_id.'");', 'id'=>$enable_field, 'style'=>$enable_default===null?'display:none;':''));
			$form->setDefaults(array($enable_field=>$enable_default));
			eval_js('admin_switch_button("'.$button_id.'",$("'.$enable_field.'").checked, "'.$sections_id.'", 1);');
			
			if (class_exists($name.'Common') && is_callable(array($name.'Common', 'admin_access_levels'))) {
				$raws = call_user_func(array($name.'Common', 'admin_access_levels'));
				if (is_array($raws))
					foreach ($raws as $s=>$v) {
						$type = isset($v['values'])?'select':'checkbox';
						$vals = isset($v['values'])?$v['values']:null;
						$s_field = $name.'__'.$s.'__switch';
						$form->addElement($type, $s_field, $this->t($v['label']), $vals);
						$form->setDefaults(array($s_field=>Base_AdminCommon::get_access($name, $s)));
						$sections[$s] = $s_field;
					}
			}
			
			$buttons[]= array(
				'label'=>$caption,
				'icon'=>$icon,
				'id'=>$button_id,
				'enable_switch'=>$enable_field,
				'sections_id'=>$sections_id,
				'sections'=>$sections
			);
		}
		Base_ThemeCommon::install_default_theme($this->get_type());
		$theme =  & $this->pack_module('Base/Theme');

		$form->assign_theme('form', $theme);
		
		$theme->assign('header', $this->t('Admin Panel Access'));
		$theme->assign('buttons', $buttons);
		$theme->display('access_panel');
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