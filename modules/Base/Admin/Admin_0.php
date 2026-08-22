<?php
/**
 * Admin class.
 * 
 * This class provides administration module.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
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

		// Wrapping div lives inside this module's own echoed output (not in
		// Base_Box's shell template) so it's always part of whatever HTML
		// actually gets patched into the page on AJAX navigation between
		// admin sections - Base_Admin stays the 'main' box container the
		// whole time (selected_module just changes what it embeds), and
		// Epesi.text()'s innerHTML patching never refreshes an ancestor
		// element's own attributes, only what's inside it. theme_adminlte's
		// GenericBrowser CSS (default.css, "mobile actions menu") uses this
		// class to keep every row's action icons visible here even under
		// the 991.98px breakpoint that collapses them into a kebab
		// elsewhere: admins need full access to actions without an extra tap.
		echo '<div class="epesi-admin-panel">';
		if($module) {
			$this->pack_module($module,null,'admin');
		} else {
			$this->list_admin_modules();
		}
		echo '</div>';
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
		uasort($mod_ok, fn($a, $b) => strcasecmp($a['label'], $b['label']));
		if (Base_AclCommon::i_am_sa()) {
			Base_ActionBarCommon::add('admin-panel', __('Admin Panel Access'), $this->create_callback_href($this->set_module(...), array('Base_Admin')));
            if (!DEMO_MODE && !HOSTING_MODE) {
       			$admin_tools_url = rtrim(get_epesi_url(), '/') . '/admin/';
	    		Base_ActionBarCommon::add('admin-tools', __('Admin Tools'), 'href="'.htmlspecialchars($admin_tools_url).'" target="_blank"');
            }
        }
                                
		$buttons = array();
		foreach($mod_ok as $name=>$caption) {
			if (method_exists($name.'Common','admin_icon')) {
				$icon = call_user_func(array($name.'Common','admin_icon'));
			} else {
				$icon = Base_ThemeCommon::get_template_file($name,'icon.png');
				if (!file_exists($icon)) $icon = Base_ThemeCommon::get_template_file('Base_Admin','icon.png');
			}
			$buttons[$caption['section']][] = array('link'=>'<a class="card text-decoration-none h-100 shadow-sm" '.$this->create_callback_href($this->set_module(...), array($name)).'>'.$caption['label'].'</a>',
						'icon'=>$icon, 'module'=>$name);
		}

		foreach ($buttons as $section=>$b) {
			$sections[$section] = array('header'=>$section, 'buttons'=>$b);
		}
		$sections = $this->sort_sections($sections);

		$theme = $this->pack_module(Base_Theme::module_name());
		$theme->assign('sections', $sections);
		$theme->display();
	}
	
	public function caption() {
		// 'selected_module' is the actual module variable set_module()/body()
		// use - was read here as 'href' (a stale/mismatched key that's never
		// set anywhere in the codebase), which meant this always fell through
		// to the generic "Administration: Control Panel" regardless of which
		// admin screen was active.
		$module = $this->get_module_variable('selected_module');
		if ($module===null) return __('Administration: Control Panel');
		$func = array($module.'Common','admin_caption');
		if(!is_callable($func)) return __('Administration: %s', array($module));
		$caption = call_user_func($func);
		// admin_caption() returns array('label'=>..., 'section'=>...) in every
		// real implementation (see list_admin_modules() above, which already
		// normalizes the same way) - passing the raw array into %s would print
		// "Administration: Array" instead of the actual label.
		if (is_array($caption)) $caption = $caption['label'] ?? null;
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
		uksort($mod_ok,strcasecmp(...));
		
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
			$form->addElement('checkbox', $enable_field, $enable_default===null?__('Access blocked'):__('Allow access'), null, array('onchange'=>'admin_switch_button("'.$button_id.'",this.checked, "'.$sections_id.'");', 'id'=>$enable_field, 'style'=>$enable_default===null?'display:none;':'', 'class'=>'epesi-switch'));
			$form->setDefaults(array($enable_field=>$enable_default));
			eval_js('admin_switch_button("'.$button_id.'",document.getElementById("'.$enable_field.'").checked, "'.$sections_id.'", 1);');
			
			if (class_exists($name.'Common') && is_callable(array($name.'Common', 'admin_access_levels'))) {
				$raws = call_user_func(array($name.'Common', 'admin_access_levels'));
				if (is_array($raws))
					foreach ($raws as $s=>$v) {
						$type = isset($v['values'])?'select':'checkbox';
						$vals = $v['values'] ?? null;
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
						DB::Execute('INSERT INTO base_admin_access (module, section, allow) VALUES (%s, %s, %d)', array($name, $s, $vals[$f] ?? 0 ));
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

		$theme = $this->pack_module(Base_Theme::module_name());

		$form->assign_theme('form', $theme);
		
		$theme->assign('header', __('Admin Panel Access'));
		$theme->assign('sections', $sections);
		$theme->display('access_panel');
	}
}
