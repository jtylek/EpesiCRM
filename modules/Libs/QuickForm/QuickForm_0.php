<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-libs
 * @subpackage QuickForm
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides saving any page as homepage for each user.
 */
class Libs_QuickForm extends Module {
	private $qf;
	
	public function construct($indicator = null, $action = '', $target = '', $on_submit = null) {
		$form_name = $this->get_path();
		if(!ereg('^[a-zA-Z_0-9|/]+$',$form_name)) //chars like [, ] can couse JS error
			trigger_error('Form name invalid: '.$form_name,E_USER_ERROR);
		if($target=='' && $action!='')
			$target = '_blank';
		if(!isset($on_submit))
			$on_submit = $this->get_submit_form_js_by_name($form_name,true,$indicator)."return false;";
		$this->qf = new HTML_QuickForm($form_name, 'post', $action, $target, array('onSubmit'=>$on_submit), true);
		$this->qf->addElement('hidden', 'submited', 0);
		eval_js_once("set_qf_sub0 = function(fn){var x=$(fn);if(x)x.submited.value=0}");
		eval_js("set_qf_sub0('".addslashes($form_name)."')");
		Base_ThemeCommon::load_css('Libs_QuickForm');
	}
	
	public function body($arg) {
		$this->qf->display($arg);
	}
	
	public function validate_with_message($success='', $failure=''){
		$ret = $this->qf->validate();
		if ($this->qf->isSubmitted()) {
			if ($ret)
				Base_StatusBarCommon::message($success);
			else
				Base_StatusBarCommon::message($failure,'warning');
		}
		return $ret;
	}
	
	public function & __call($func_name, $args) {
		if (is_object($this->qf))
			$return = & call_user_func_array(array(&$this->qf, $func_name), $args);
		else
			trigger_error("QuickFrom object doesn't exists", E_USER_ERROR);
		return $return;
	}
	
	public function get_submit_form_js($submited=true, $indicator=null) {
		if (!is_object($this->qf))
			throw new Exception("QuickFrom object doesn't exists");
		$form_name = $this->qf->getAttribute('name');
		return $this->get_submit_form_js_by_name($form_name,$submited,$indicator); 
	}
	public function get_submit_form_href($submited=true, $indicator=null) {
		 return ' href="javascript:void(0)" onClick="'.$this->get_submit_form_js($submited,$indicator).'" ';
	}
	
	private function get_submit_form_js_by_name($form_name, $submited, $indicator) {
		if(!isset($indicator)) $indicator='processing...';
		$fast = "+'&".str_replace('&amp;','&',http_build_query(array('__action_module__'=>$this->get_parent_path())))."'"; 
		$s = str_replace('this',"$('".addslashes($form_name)."')",Libs_QuickFormCommon::get_on_submit_actions())."Epesi.href($('".addslashes($form_name)."').serialize()".$fast.", '".Epesi::escapeJS($indicator)."');";
		if($submited)
	 		$s = "$('".addslashes($form_name)."').submited.value=1;".$s."$('".addslashes($form_name)."').submited.value=0;";
		return $s;
	}

	public function assign_theme($name, & $theme, $renderer=null){ 
		if(!isset($renderer)) $renderer = & new HTML_QuickForm_Renderer_TCMSArraySmarty(); 
		$this->accept($renderer); 
		$form_data = $renderer->toArray();
		$theme->assign($name.'_name', $this->getAttribute('name')); 
		$theme->assign($name.'_data', $form_data);
		$theme->assign($name.'_open', $form_data['javascript'].'<form '.$form_data['attributes'].'>'.$form_data['hidden']."\n");
		$theme->assign($name.'_close', "</form>\n");
	} 
	
	public function add_array($info, & $default_js=''){
		$l = $this->init_module('Base/Lang');
		foreach($info as $v){
			if(!isset($v['param'])) $v['param']=null;
			if(!isset($v['values'])) $v['values']=null;
			switch($v['type']){
				case 'select':
					$this -> addElement('select',$v['name'],$v['label'],$v['values'],$v['param']);
					$default_js .= 'e = $(\''.$this->getAttribute('name').'\').'.$v['name'].';'.
					'for(i=0; i<e.length; i++) if(e.options[i].value==\''.$v['default'].'\'){e.options[i].selected=true;break;};';
					break;
				
				case 'static':
				case 'header':
					$this -> addElement($v['type'],isset($v['name'])?$v['name']:null,$v['label'],isset($v['values'])?$v['values']:'');
					break;
					
				case 'radio':
					$radio = array();
					$label = $v['label'];
					foreach($v['values'] as $k=>$x) {
						$this -> addElement('radio',$v['name'],$label,$l->ht($x),$k,$v['param']);
						$label = '';
					}
					$default_js .= 'e = $(\''.$this->getAttribute('name').'\').'.$v['name'].';'.
					'for(i=0; i<e.length; i++){e[i].checked=false;if(e[i].value==\''.$v['default'].'\')e[i].checked=true;};';
					break;
					
				case 'bool':
				case 'checkbox':
					$this -> addElement('checkbox',$v['name'],$v['label'],$v['values'],$v['param']);
					$default_js .= '$(\''.$this->getAttribute('name').'\').'.$v['name'].'.checked = '.$v['default'].';';
					break;
				
				case 'numeric':
					if(!isset($v['rule']) || !is_array($v['rule'])) $v['rule']=array();
					$v['rule'][] = array('type'=>'numeric','message'=>Base_LangCommon::ts('Libs_QuickForm','This is not valid number'));
				case 'password':
				case 'text':
				case 'textarea':
					$obj = $this -> addElement($v['type'],$v['name'],$v['label'],$v['param']);
					$default_js .= '$(\''.$this->getAttribute('name').'\').'.$v['name'].'.value = \''.$v['default'].'\';';
					break;
							
				case 'fckeditor':
					$obj = $this -> addElement($v['type'],$v['name'],$v['label'],$v['param']);
					$obj->setFCKProps('400','125',false);
					$default_js .= '$(\''.$this->getAttribute('name').'\').'.$v['name'].'.value = \''.$v['default'].'\';';
					break;
							
				default:
					trigger_error('Invalid type: '.$v['type'],E_USER_ERROR);
			}
			if(isset($v['default'])) $this->setDefaults(array($v['name']=>$v['default']));
			
			if (isset($v['rule'])) {
				$i = 0;
				if(isset($v['rule']['message']) && isset($v['rule']['type'])) $v['rule'] = array($v['rule']);
				foreach ($v['rule'] as $r) {
					if (!isset($r['message'])) trigger_error('No error message specified for field '.$v['name'], E_USER_ERROR);
					if (!isset($r['type'])) trigger_error('No error type specified for field '.$v['name'], E_USER_ERROR);
					if ($r['type']=='callback') {
						if (!isset($r['func'])) trigger_error('Invalid parameter specified for rule definition for field '.$v['name'], E_USER_ERROR);
						if(is_string($r['func']))
							$this->registerRule($v['name'].$i.'_rule', 'callback', $r['func']);
						elseif(is_array($r['func']))
							$this->registerRule($v['name'].$i.'_rule', 'callback', $r['func'][1], $r['func'][0]);
						else
							trigger_error('Invalid parameter specified for rule definition for field '.$v['name'], E_USER_ERROR);
						if(isset($r['param']) && $r['param']=='__form__')
							$r['param'] = &$this;
//						print($v['name'].', '.$r['message'].', '.$r['type'].', '.(isset($r['param'])?$r['param']:'').'<br>');
						$this->addRule($v['name'], $r['message'], $v['name'].$i.'_rule', isset($r['param'])?$r['param']:null);
					} else {
						if ($r['type']=='regex' && !isset($r['param'])) trigger_error('No regex defined for a rule for field '.$v['name'], E_USER_ERROR);
//						print($v['name'].', '.$r['message'].', '.$r['type'].', '.(isset($r['param'])?$r['param']:'').'<br>');
						$this->addRule($v['name'], $r['message'], $r['type'], isset($r['param'])?$r['param']:null);
					}
					$i++;
				}
			}
			if (isset($v['filter']))
				foreach ($v['filter'] as $r) {
					$this->applyFilter($v['name'],$r);
				}
		}
	}
			
	public function add_table($table_name, array $cols, &$js='') {
		$meta_table = DB::MetaColumns($table_name);
		$arr = array();
		foreach($cols as $k=>$v) {
			if(is_string($k)) {
				if(is_array($v) && !isset($v['name']))
					$v['name'] = $k;
				elseif(is_string($v))
					$v = array('name'=>$k, 'label'=>$v);
				else
					trigger_error('Invalid arguments to add_table quick form method',E_USER_ERROR);
			}
			$name = strtoupper($v['name']);
			$meta = & $meta_table[$name];
			if(!is_object($meta)) {
				$arr[] = $v;
				continue;
			}
			if(!isset($v['default']) && $meta->has_default) $v['default'] = $meta->default_value;
			$type = DB::dict()->MetaType($meta);
			if(!isset($v['type']))
				switch($type) {
					case 'C': 
						$v['type']='text';
						break;
					case 'X':
						$v['type']='textarea';
						break;
					case 'I':
					case 'I2':
					case 'I4':
					case 'I8':
					case 'F':
						$v['type']='numeric';
						break;
					case 'I1':
						$v['type']='checkbox';
						break;
				}
			if(($v['type']=='text' || $v['type']=='password' || $v['type']=='textarea') && !isset($v['default']))
				$v['default']='';
			if($meta->max_length>0) {
				if(!isset($v['rule'])) $v['rule'] = array();
				$v['rule'][] = array('message'=>Base_LangCommon::ts('Libs_QuickForm','Text too long'), 'type'=>'maxlength', 'param'=>$meta->max_length);
				if(!isset($v['param'])) $v['param'] = array();
				if(is_string($v['param'])) $v['param'].=' maxlength=\''.$meta->max_length.'\'';
					else $v['param']['maxlength'] = $meta->max_length;
			}
			if($meta->not_null) {
				if(!isset($v['rule'])) $v['rule'] = array();
				$v['rule'][] = array('message'=>Base_LangCommon::ts('Libs_QuickForm','Field required'), 'type'=>'required');
			}
			$arr[] = $v;
		}
		$this->add_array($arr,$js);
	}
}
?>
