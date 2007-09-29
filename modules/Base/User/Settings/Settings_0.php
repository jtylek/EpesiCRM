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

	public function body() {
		global $base;
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
					$this->add_module_settings_to_form($v,$f,$module_name);
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
		foreach($info as $v){
			if ($v['type']=='select'){
				$select = array(); 
				foreach($v['values'] as $k=>$x) $select[$k] = $this->lang->ht($x);
				$f -> addElement('select',$module.self::$sep.$v['name'],$this->lang->t($v['label']),$select);
				$this->set_default_js .= 'e = $(\''.$f->getAttribute('name').'\').'.$module.self::$sep.$v['name'].';'.
										'for(i=0; i<e.length; i++) if(e.options[i].value==\''.$v['default'].'\'){e.options[i].selected=true;break;};';
			} elseif ($v['type']=='static' || $v['type']=='header') {
				$f -> addElement($v['type'],$module.self::$sep.$v['name'],$this->lang->t($v['label']),$this->lang->t($v['values']));
			} elseif ($v['type']=='radio') {
				$radio = array();
				$label = $this->lang->t($v['label']);
				foreach($v['values'] as $k=>$x) {
					$f -> addElement('radio',$module.self::$sep.$v['name'],$label,$this->lang->ht($x),$k);
					$label = '';
				}
				$this->set_default_js .= 'e = $(\''.$f->getAttribute('name').'\').'.$module.self::$sep.$v['name'].';'.
										'for(i=0; i<e.length; i++){e[i].checked=false;if(e[i].value==\''.$v['default'].'\')e[i].checked=true;};';
			} elseif ($v['type']=='bool' || $v['type']=='checkbox') {
				$f -> addElement('checkbox',$module.self::$sep.$v['name'],$this->lang->t($v['label']));
				$this->set_default_js .= '$(\''.$f->getAttribute('name').'\').'.$module.self::$sep.$v['name'].'.checked = '.$v['default'].';';
			} elseif ($v['type']=='text' || $v['type']=='textarea' || $v['type']=='fckeditor') {
				$obj = $f -> addElement($v['type'],$v['name'],$this->lang->t($v['label']));
				if($v['type']=='fckeditor')
					$obj->setFCKProps('400','125',false);
				$this->set_default_js .= '$(\''.$f->getAttribute('name').'\').'.$module.self::$sep.$v['name'].'.value = \''.$v['default'].'\';';
			} else trigger_error('Invalid type: '.$v['type'],E_USER_ERROR);
			if (isset($v['rule'])) {
				$i = 0;
				foreach ($v['rule'] as $r) {
					if (!isset($r['message'])) trigger_error('No error message specified for field '.$v['name'], E_USER_ERROR);
					if (!isset($r['type'])) trigger_error('No error type specified for field '.$v['name'], E_USER_ERROR);
					if ($r['type']=='callback') {
						if (!isset($r['func'])) trigger_error('Invalid parameter specified for rule definition for field '.$v['name'], E_USER_ERROR);
						if(is_string($r['func']))
							$f->registerRule($v['name'].$i.'_rule', 'callback', $r['func']);
						elseif(is_array($r['func']))
							$f->registerRule($v['name'].$i.'_rule', 'callback', $r['func'][1], $r['func'][0]);
						else
							trigger_error('Invalid parameter specified for rule definition for field '.$v['name'], E_USER_ERROR);
						if(isset($r['param']) && $r['param']=='__form__')
							$r['param'] = &$f;
						$f->addRule($module.self::$sep.$v['name'], $this->lang->t($r['message']), $v['name'].$i.'_rule', isset($r['param'])?$r['param']:null);
					} else {
						if ($r['type']=='regex' && !isset($r['param'])) trigger_error('No regex defined for a rule for field '.$v['name'], E_USER_ERROR);
						$f->addRule($module.self::$sep.$v['name'], $this->lang->t($r['message']), $r['type'], isset($r['param'])?$r['param']:null);
					}
					$i++;
				}
			}
			
			$f -> setDefaults(array($module.self::$sep.$v['name']=>Base_User_SettingsCommon::get($module,$v['name'])));
		}
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
		global $base;
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
	
}

?>