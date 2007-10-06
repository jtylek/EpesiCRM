<?php
/**
 * User_Settings class.
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-base-extra
 * @subpackage user-settings
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_User_Settings extends Module {
	private $lang;
	private $module;
	private $set_default_js;
	private static $sep = "__";
	private $indicator = '';

	public function body() {
		$this->lang = & $this->init_module('Base/Lang');
		if (isset($_REQUEST['module'])) $module = $_REQUEST['module']; 
		else $module = $this->get_module_variable('module');
		if ($module=='__NONE__') $module = null;
		
		if (!$module || $this->is_back()) {
			$this->main_page();
			return;
		}
		$this->module = $module;
		
		$this->set_module_variable('module',$module);
		$f = &$this->init_module('Libs/QuickForm',$this->lang->ht('Saving settings'),'settings');
		list($module_name,$module_part) = explode(self::$sep,$module);
		if(method_exists($module_name.'Common', 'user_settings')) {
			$menu = call_user_func(array($module_name.'Common','user_settings'));
			if (is_array($menu)) { 
				foreach($menu as $k=>$v) if ($k==$module_part){
					$f->addElement('header',null,$this->lang->t($k));
					$this->indicator = ': '.$k;
					$this->add_module_settings_to_form($v,$f,$module_name);
					break;
				}
			}
		}

		$defaults = HTML_QuickForm::createElement('button','defaults',$this->lang->ht('Restore defaults'), 'onClick="'.$this->set_default_js.'"');
		$submit = HTML_QuickForm::createElement('submit','submit',$this->lang->ht('OK'));
		$cancel = HTML_QuickForm::createElement('button','cancel',$this->lang->ht('Cancel'), $this->create_back_href());
		$f->addGroup(array($defaults, $submit,$cancel));

		if($f->validate()) {
			$f->process(array(& $this, 'submit_settings'));
			location(array());
		} else
			$f->display();
		return;
	}

	public function submit_settings($values) {
		list($module_name,$module_part) = explode(self::$sep,$this->module);
		$reload = false;
		if(method_exists($module_name.'Common', 'user_settings')) {
			$menu = call_user_func(array($module_name.'Common','user_settings'));
			if(!is_array($menu)) continue;
			foreach($menu as $k=>$v) if ($k==$module_part){
				$this->set_user_preferences($v,$values,$module_name);
				if (!$reload) 
					foreach($v as $f) 
						if (isset($f['reload']) && $f['reload']!=0) {
							$reload = true;
							break;
						}
			}
		}
		$this->unset_module_variable('module');
		Base_StatusBarCommon::message($this->lang->ht('Setting saved'.($reload?' - reloading page':'')));
		if ($reload) eval_js('setTimeout(\'document.location=\\\'index.php\\\'\',\'3000\')');
		return true;
	}
	
	private function add_module_settings_to_form($info, &$f, $module){
		$defaults = array();
		foreach($info as & $v){
			if(isset($v['label'])) $v['label'] = $this->lang->t($v['label']);
			$v['name'] = $module.self::$sep.$v['name'];
			if(isset($v['values']) && is_array($v['values']))
				foreach($v['values'] as &$x) 
					$x = $this->lang->ht($x);
			if (isset($v['rule']))
				foreach ($v['rule'] as & $r)
					if (isset($r['message'])) $r = $this->lang->t($r['message']);
			$defaults = array_merge($defaults,array($v['name']=>Base_User_SettingsCommon::get($module,$v['name'])));
		}
		$this->set_default_js = '';
		$f -> add_array($info, $this->set_default_js);
		$f -> setDefaults($defaults);

	}
	
	private function set_user_preferences($info,$values,$module){
		foreach($info as $v){
			Base_User_SettingsCommon::save($module,$v['name'],isset($values[$module.self::$sep.$v['name']])?$values[$module.self::$sep.$v['name']]:0);
		}
	}
	
	public function main_page(){
		if (!Acl::is_user()) {
			print('Log in to change your settings.');
		}
		$this->lang = & $this->init_module('Base/Lang');
		$modules = array(); 
		foreach(ModuleManager::$modules as $name=>$obj) {
			if(method_exists($obj['name'].'Common', 'user_settings')) {
				$menu = call_user_func(array($obj['name'].'Common','user_settings'));
				if(!is_array($menu)) continue;
				foreach($menu as $k=>$v)
					if ($v!='callbody') $modules[$k] = array('action'=>array('module'=>$obj['name'].self::$sep.$k),'module_name'=>$obj['name']);
					else $modules[$k] = array('action'=>array('box_main_module'=>$obj['name']),'module_name'=>$obj['name']);
			}
		}
		
		$links = array();
		foreach($modules as $caption=>$arg)
			$links[$arg['module_name']]= '<a '.$this->create_href($arg['action']).'>'.$this->lang->t($caption).'</a>';
		$theme =  & $this->pack_module('Base/Theme');
		$theme->assign('header', $this->lang->t('User Settings'));
		$theme->assign('links', $links);
		$theme->display();
	}
	
	public function caption() {
		return "My settings".$this->indicator;
	}
}

?>