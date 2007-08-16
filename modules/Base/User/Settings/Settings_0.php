<?php
/**
 * User_Settings class.
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 * @subpackage user-settings
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_User_Settings extends Module {
	private $lang;
	private $module; 

	public function body($arg) {
		global $base;
		$this->lang = & $this->init_module('Base/Lang');
		if ($_REQUEST['module']) $module = $_REQUEST['module']; 
		else $module = $this->get_module_variable('module');
		if ($module=='__NONE__') $module = null;
		
		if (!$module || $this->is_back()) {
			$this->main_page();
			return;
		}
		$this->module = $module;
		
		$this->set_module_variable('module',$module);
		$f = &$this->init_module('Libs/QuickForm',$this->lang->ht('Saving settings'),'settings');
		list($module_name,$module_part) = explode('::',$module);
		if(method_exists($module_name.'Common', 'user_settings')) {
			$menu = call_user_func(array($module_name.'Common','user_settings'));
			if (is_array($menu)) { 
				foreach($menu as $k=>$v) if ($k==$module_part){
					$f->addElement('header',null,$this->lang->t($k));
					$this->add_module_settings_to_form($v,$f,$module_name);
				}
			}
		}

		$submit = HTML_QuickForm::createElement('submit','submit',$this->lang->ht('OK'));
		$cancel = HTML_QuickForm::createElement('button','cancel',$this->lang->ht('Cancel'), $this->create_back_href());
		$f->addGroup(array($submit,$cancel));

		if($f->validate()) {
			$f->process(array(& $this, 'submit_settings'));
			location(array());
		} else
			$f->display();
		return;
	}

	public function submit_settings($values) {
		list($module_name,$module_part) = explode('::',$this->module);
		$reload = false;
		if(method_exists($module_name.'Common', 'user_settings')) {
			$menu = call_user_func(array($module_name.'Common','user_settings'));
			if(!is_array($menu)) continue;
			foreach($menu as $k=>$v) if ($k==$module_part){
				$this->set_user_preferences($v,$values,$module_name);
				if (!$reload) 
					foreach($v as $f) 
						if ($f['reload']) {
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
		foreach($info as $v){
			if ($v['type']=='select'){
				$select = array(); 
				foreach($v['values'] as $k=>$x) $select[$k] = $this->lang->ht($x);
				$f -> addElement('select',$module.'::'.$v['name'],$this->lang->t($v['label']),$select);
			}
			if ($v['type']=='radio'){
				$radio = array();
				$label = $this->lang->t($v['label']);
				foreach($v['values'] as $k=>$x) {
					$f -> addElement('radio',$module.'::'.$v['name'],$label,$this->lang->ht($x),$k);
					$label = '';
				}
			}
			if ($v['type']=='bool')
				$f -> addElement('checkbox',$module.'::'.$v['name'],$this->lang->t($v['label']));
			if ($v['type']=='text')
				$f -> addElement('text',$module.'::'.$v['name'],$this->lang->t($v['label']));
			$f -> setDefaults(array($module.'::'.$v['name']=>Base_User_SettingsCommon::get_user_settings($module,$v['name'])));
		}
	}
	
	private function set_user_preferences($info,$values,$module){
		foreach($info as $v){
			Base_User_SettingsCommon::save_user_settings($module,$v['name'],$values[$module.'::'.$v['name']]);
		}
	}
	
	public function main_page(){
		if (!Base_AclCommon::i_am_user()) {
			print('Log in to change your settings.');
		}
		global $base;
		$this->lang = & $this->init_module('Base/Lang');
		$modules = array(); 
		foreach(ModuleManager::$modules as $name=>$obj) {
			if(method_exists($obj['name'].'Common', 'user_settings')) {
				$menu = call_user_func(array($obj['name'].'Common','user_settings'));
				if(!is_array($menu)) continue;
				foreach($menu as $k=>$v)
					if ($v!='callbody') $modules[$k] = array('action'=>array('module'=>$obj['name'].'::'.$k),'module_name'=>$obj['name']);
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
	
}

?>