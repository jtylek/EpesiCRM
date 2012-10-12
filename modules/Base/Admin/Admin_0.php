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

	public function set_module($module) {
		$this->set_module_variable('selected_module', $module);
	}
		
	public function body() {
		$module = $this->get_module_variable('selected_module', false);

		if($module) {
			$this->pack_module($module,null,'admin');
		} else {
			$this->list_admin_modules();
		}
		
	} 
		
	public function reset() {
		$this->unset_module_variable('selected_module');
		location(array());
	}
	
	public function sort_sections($tmp) {
		$sections = array();
		// Apply arbitrary order
		foreach (array(__('User Management'), __('Features Configuration'), __('Data'), __('Regional Settings'), __('Server Configuration')) as $s) {
			if (isset($tmp[$s])) {
				$sections[$s] = $tmp[$s];
				unset($tmp[$s]);
			}
		}
		foreach ($tmp as $s=>$c)
			$sections[$s] = $c;
		// Apply arbitrary order
		return $sections;
	}
	
	private function list_admin_modules() {
		$mod_ok = array();
		$sections = array();
		
		$cmr = ModuleManager::call_common_methods('admin_caption');
		foreach($cmr as $name=>$caption) {
			if(!ModuleManager::check_access($name,'admin') || $name=='Base_Admin') continue;
			if (Base_AdminCommon::get_access($name)==false) continue;
			if(!isset($caption)) continue;
			if (!is_array($caption)) {
				$caption = array('label'=>$caption);
			}
			if (!isset($caption['section'])) $caption['section'] = __('Misc');
			$mod_ok[$name] = $caption;
		}
		if (Base_AclCommon::i_am_sa())
			Base_ActionBarCommon::add('settings', __('Admin Panel Access'), $this->create_callback_href(array($this, 'set_module'), array('Base_Admin')));

		$buttons = array();
		foreach($mod_ok as $name=>$caption) {
			if (method_exists($name.'Common','admin_icon')) {
				$icon = call_user_func(array($name.'Common','admin_icon'));
			} else {
				$icon = Base_ThemeCommon::get_template_file($name,'icon.png');
				if (!file_exists($icon)) $icon = Base_ThemeCommon::get_template_file('Base_Admin','icon.png');
			}
			$buttons[$caption['section']][] = array('link'=>'<a '.$this->create_callback_href(array($this, 'set_module'), array($name)).'>'.$caption['label'].'</a>',
						'icon'=>$icon);
		}

		foreach ($buttons as $section=>$b) {
			$sections[$section] = array('header'=>$section, 'buttons'=>$b);
		}
		$sections = $this->sort_sections($sections);

		$theme = $this->pack_module('Base/Theme');
		$theme->assign('sections', $sections);
		$theme->display();
	}
	
	public function caption() {
		$module = $this->get_module_variable('href');
		if ($module===null) return __('Administration: Control Panel');
		$func = array($module.'Common','admin_caption');
		if(!is_callable($func)) return __('Administration: %s', array($module));
		$caption = call_user_func($func);
		if($caption) return __('Administration: %s',array($caption));
		return __('Administration');
	}
	
	public function admin() {
		if(!Base_AclCommon::i_am_sa() || $this->is_back()) {
			$this->parent->reset();
			return;
		}
		Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
		
		$cmr = ModuleManager::call_common_methods('admin_caption');
		foreach($cmr as $name=>$caption) {
			if(!ModuleManager::check_access($name,'admin') || $name=='Base_Admin') continue;
			if (!isset($caption)) continue;
			if (!is_array($caption)) {
				$caption = array('label'=>$caption);
			}
			if (!isset($caption['section'])) $caption['section'] = __('Misc');
			$mod_ok[$name] = $caption;
		}
		uksort($mod_ok,'strcasecmp');
		
		$form = $this->init_module('Libs_QuickForm');
		
		$buttons = array();
		load_js('modules/Base/Admin/js/main.js');
		foreach($mod_ok as $name=>$caption) {
			if (method_exists($name.'Common','admin_icon')) {
				$icon = call_user_func(array($name.'Common','admin_icon'));
			} else {
				$icon = Base_ThemeCommon::get_template_file($name,'icon.png');
				if (!file_exists($icon)) $icon = Base_ThemeCommon::get_template_file('Base_Admin','icon.png');
			}
			$button_id = $name.'__button';
			$enable_field = $name.'_enable';
			$sections = array();
			$sections_id = $name.'__sections';

			$enable_default = Base_AdminCommon::get_access($name, '', true);
			$form->addElement('checkbox', $enable_field, $enable_default===null?__('Access blocked'):__('Allow access'), null, array('onchange'=>'admin_switch_button("'.$button_id.'",this.checked, "'.$sections_id.'");', 'id'=>$enable_field, 'style'=>$enable_default===null?'display:none;':''));
			$form->setDefaults(array($enable_field=>$enable_default));
			eval_js('admin_switch_button("'.$button_id.'",$("'.$enable_field.'").checked, "'.$sections_id.'", 1);');
			
			if (class_exists($name.'Common') && is_callable(array($name.'Common', 'admin_access_levels'))) {
				$raws = call_user_func(array($name.'Common', 'admin_access_levels'));
				if (is_array($raws))
					foreach ($raws as $s=>$v) {
						$type = isset($v['values'])?'select':'checkbox';
						$vals = isset($v['values'])?$v['values']:null;
						$s_field = $name.'__'.$s.'__switch';
						$form->addElement($type, $s_field, $v['label'], $vals);
						$form->setDefaults(array($s_field=>Base_AdminCommon::get_access($name, $s, true)));
						$sections[$s] = $s_field;
					}
			}
			
			$buttons[$caption['section']][$name]= array(
				'label'=>$caption['label'],
				'icon'=>$icon,
				'id'=>$button_id,
				'enable_switch'=>$enable_field,
				'sections_id'=>$sections_id,
				'sections'=>$sections
			);
		}
		
		if ($form->validate()) {
			$vals = $form->exportValues();
			DB::Execute('DELETE FROM base_admin_access');
			foreach ($buttons as $section=>$bs)
				foreach ($bs as $name=>$b) {
					DB::Execute('INSERT INTO base_admin_access (module, section, allow) VALUES (%s, %s, %d)', array($name, '', (isset($vals[$b['enable_switch']]) && $vals[$b['enable_switch']])?1:0 ));
					foreach ($b['sections'] as $s=>$f)
						DB::Execute('INSERT INTO base_admin_access (module, section, allow) VALUES (%s, %s, %d)', array($name, $s, isset($vals[$f])?$vals[$f]:0 ));
				}
			$this->parent->reset();
			return;
		}

		Base_ActionBarCommon::add('save',__('Save'),$form->get_submit_form_href());

		$sections = array();
		foreach ($buttons as $section=>$b) {
			$sections[$section] = array('header'=>$section, 'buttons'=>$b);
		}
		$sections = $this->sort_sections($sections);

		$theme = $this->pack_module('Base/Theme');

		$form->assign_theme('form', $theme);
		
		$theme->assign('header', __('Admin Panel Access'));
		$theme->assign('sections', $sections);
		$theme->display('access_panel');
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