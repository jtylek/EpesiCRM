<?php
/** 
 * Something like igoogle
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @version 0.1
 * @package epesi-base-extra
 * @subpackage activeboard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ActiveBoard extends Module {
	private $lang;
	private $set_default_js='';
	
	public function body() {
		$this->lang = $this->init_module('Base/Lang');
		Base_ActionBarCommon::add('add','Add applet',$this->create_callback_href(array($this,'applets_list')));
		load_js_inline($this->get_module_dir().'ab.js');
		print('<div id="activeboard">');
		for($j=0; $j<3; $j++) {
			print('<div id="activeboard_applets_'.$j.'" style="float:left;width:33%;min-height:100px">');
			
			$ret = DB::Execute('SELECT id,module_name FROM base_activeboard_applets WHERE col=%d AND user_login_id=%d ORDER BY pos',array($j,Base_UserCommon::get_my_user_id()));
			while($row = $ret->FetchRow()) {
				if(ModuleManager::is_installed($row['module_name'])==-1) {//if its invalid entry
					$this->delete_applets($row['module_name']);
					continue;
				}
					
				$m = $this->init_module($row['module_name'],null,$row['id']);
				$th = $this->init_module('Base/Theme');
				$th->assign('handle_class','handle');
				$th->assign('caption',call_user_func(array($row['module_name'].'Common', 'applet_caption')));
				$th->assign('toggle','<a class="toggle">=</a>');
				$th->assign('remove','<a class="remove" '.$this->create_confirm_callback_href($this->lang->t('Are you sure?'),array($this,'delete_applet'),$row['id']).'>x</a>');
				if(method_exists($row['module_name'].'Common', 'applet_settings'))
					$th->assign('configure','<a class="configure" '.$this->create_callback_href(array($this,'configure_applet'),array($row['id'],$row['module_name'])).'>c</a>');
				$th->assign('content','<div class="content">'.
						$this->get_html_of_module($m,array($this->get_values($row['id'],$row['module_name'])),'applet').
						'</div>');
				print('<div class="applet" id="ab_item_'.$row['id'].'">');
				$th->display();
				print('</div>');
			}
			print('</div>');
		}
		print('</div>');
		eval_js('wait_while_null("Effect","activeboard_activate()")');
	}
	
	public function applets_list() {
		$fc = $this->get_module_variable('first_conf');
		if(isset($fc)) {
			$mod = $this->get_module_variable('mod_conf');
			if(!$this->configure_applet($fc,$mod)) {
				$this->unset_module_variable('first_conf');
				$this->unset_module_variable('mod_conf');
				return false;
			}
			return true;
		}
		
		$tipmod = $this->init_module('Utils/Tooltip');
		foreach(ModuleManager::$modules as $name=>$obj) {
			if(method_exists($obj['name'].'Common', 'applet_caption')) {
				$attrs = '';
				if(method_exists($obj['name'].'Common', 'applet_info')) {
					$out = '';
					$ret = call_user_func(array($obj['name'].'Common', 'applet_info'));
					if(is_array($ret)) {
						$out .= '<table>';
						foreach($ret as $k=>$v)
							$out .= '<tr><td>'.$k.'</td><td>'.$v.'</td></tr>';
						$out .= '</table>';
					} elseif(is_string($ret))
						$out = $ret;
					else
						trigger_error('Invalid applet info for module: '.$obj['name'],E_USER_ERROR);
					$attrs .= $tipmod->open_tag_attrs($out).' ';
				}
				print('<a '.$attrs.$this->create_callback_href(array($this,'add_applet'),$obj['name']).'>'.call_user_func(array($obj['name'].'Common', 'applet_caption')).'</a><br>');
			}
		}
		return true;
	}
	
	public function add_applet($mod) {
		DB::Execute('INSERT INTO base_activeboard_applets(user_login_id,module_name) VALUES (%d,%s)',array(Base_UserCommon::get_my_user_id(),$mod));
		$this->set_module_variable('first_conf',DB::Insert_ID('base_activeboard_applets','id'));
		$this->set_module_variable('mod_conf',$mod);
		Base_StatusBarCommon::message(Base_LangCommon::ts($this->get_type(),'Applet added'));
	}

	public static function delete_applet($id) {
		DB::Execute('DELETE FROM base_activeboard_settings WHERE applet_id=%d',array($id));
		DB::Execute('DELETE FROM base_activeboard_applets WHERE id=%d AND user_login_id=%d',array($id,Base_UserCommon::get_my_user_id()));
	}

	public static function delete_applets($module) {
		$module = str_replace('/','_',$module);
		$ret = DB::GetAll('SELECT id FROM base_activeboard_applets WHERE module_name=%s',array($module));
		foreach($ret as $row)
			DB::Execute('DELETE FROM base_activeboard_settings WHERE applet_id=%d',array($row['id']));
		DB::Execute('DELETE FROM base_activeboard_applets WHERE module_name=%s',array($module));
	}

	public function configure_applet($id,$mod) {
		if($this->is_back() || !method_exists($mod.'Common', 'applet_settings')) return false;
		
		$this->lang = $this->init_module('Base/Lang');
		$f = &$this->init_module('Libs/QuickForm',$this->lang->ht('Saving settings'),'settings');
		$f->addElement('header',null,$this->lang->t(call_user_func(array($mod.'Common','applet_caption'))));
		
		$menu = call_user_func(array($mod.'Common','applet_settings'));
		if (is_array($menu)) 
			$this->add_module_settings_to_form($menu,$f,$id,$mod);
		else
			trigger_error('Invalid applet settings function: '.$mod,E_USER_ERROR);

		$defaults = HTML_QuickForm::createElement('button','defaults',$this->lang->ht('Restore defaults'), 'onClick="'.$this->set_default_js.'"');
		$submit = HTML_QuickForm::createElement('submit','submit',$this->lang->ht('OK'));
		$cancel = HTML_QuickForm::createElement('button','cancel',$this->lang->ht('Cancel'), $this->create_back_href());
		$f->addGroup(array($defaults, $submit,$cancel));

		if($f->validate()) {
			//$f->process(array(& $this, 'submit_settings'));
			$submited = $f->exportValues();
			$defaults = $this->get_default_values($mod);
			$old = $this->get_values($id,$mod);
			foreach($defaults as $name=>$def_value)
				if($submited[$name]!=$old[$name]) {
					if($submited[$name]==$def_value)
						DB::Execute('DELETE FROM base_activeboard_settings WHERE applet_id=%d AND name=%s',array($id,$name));
					else
						DB::Replace('base_activeboard_settings', array('applet_id'=>$id, 'name'=>$name, 'value'=>$submited[$name]), array('applet_id','name'), true);
				}
			return false;
		}
		$f->display();
		return true;

	}
	
	private function get_default_values($mod) {
		static $variables;
		if (isset($variables[$mod]))
			return $variables[$mod];

		$variables[$mod] = array();
		if(method_exists($mod.'Common', 'applet_settings')) {
			$menu = call_user_func(array($mod.'Common','applet_settings'));
			foreach($menu as $v) 
				$variables[$mod][$v['name']] = $v['default'];
		}
		return $variables[$mod];
	}
	
	private function get_values($id,$mod) {
		$variables = $this->get_default_values($mod);

		$ret = DB::Execute('SELECT name,value FROM base_activeboard_settings WHERE applet_id=%d',array($id));
		while($v = $ret->FetchRow())
			$variables[$v['name']] = $v['value'];
		
		return $variables;
	}

	private function add_module_settings_to_form($info, &$f, $id, $module){
		$values = $this->get_values($id,$module);
		foreach($info as $v){
			if ($v['type']=='select'){
				$select = array(); 
				foreach($v['values'] as $k=>$x) $select[$k] = $this->lang->ht($x);
				$f -> addElement('select',$v['name'],$this->lang->t($v['label']),$select);
				$this->set_default_js .= 'e = document.getElementById(\''.$f->getAttribute('name').'\').'.$v['name'].';'.
										'for(i=0; i<e.length; i++) if(e.options[i].value==\''.$v['default'].'\'){e.options[i].selected=true;break;};';
			} elseif ($v['type']=='static' || $v['type']=='header') {
				$f -> addElement($v['type'],$v['name'],$this->lang->t($v['label']),$this->lang->t($v['values']));
			} elseif ($v['type']=='radio') {
				$radio = array();
				$label = $this->lang->t($v['label']);
				foreach($v['values'] as $k=>$x) {
					$f -> addElement('radio',$v['name'],$label,$this->lang->ht($x),$k);
					$label = '';
				}
				$this->set_default_js .= 'e = document.getElementById(\''.$f->getAttribute('name').'\').'.$v['name'].';'.
										'for(i=0; i<e.length; i++){e[i].checked=false;if(e[i].value==\''.$v['default'].'\')e[i].checked=true;};';
			} elseif ($v['type']=='bool' || $v['type']=='checkbox') {
				$f -> addElement('checkbox',$v['name'],$this->lang->t($v['label']));
				$this->set_default_js .= 'document.getElementById(\''.$f->getAttribute('name').'\').'.$v['name'].'.checked = '.$v['default'].';';
			} elseif ($v['type']=='text' || $v['type']=='textarea') {
				$f -> addElement($v['type'],$v['name'],$this->lang->t($v['label']));
				$this->set_default_js .= 'document.getElementById(\''.$f->getAttribute('name').'\').'.$v['name'].'.value = \''.$v['default'].'\';';
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
						$f->addRule($v['name'], $this->lang->t($r['message']), $v['name'].$i.'_rule', isset($r['param'])?$r['param']:null);
					} else {
						if ($r['type']=='regex' && !isset($r['param'])) trigger_error('No regex defined for a rule for field '.$v['name'], E_USER_ERROR);
						$f->addRule($v['name'], $this->lang->t($r['message']), $r['type'], isset($r['param'])?$r['param']:null);
					}
					$i++;
				}
			}
		}
		$f -> setDefaults($values);
	}

}

?>