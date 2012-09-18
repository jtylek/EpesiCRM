<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage QuickForm
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

require_once('Renderer/TCMSArraySmarty.php');
require_once('Renderer/TCMSDefault.php');

$GLOBALS['_HTML_QuickForm_default_renderer'] = new HTML_QuickForm_Renderer_TCMSDefault();
$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['multiselect'] = array('modules/Libs/QuickForm/FieldTypes/multiselect/multiselect.php','HTML_QuickForm_multiselect');
$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['autocomplete'] = array('modules/Libs/QuickForm/FieldTypes/autocomplete/autocomplete.php','HTML_QuickForm_autocomplete');
$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['automulti'] = array('modules/Libs/QuickForm/FieldTypes/automulti/automulti.php','HTML_QuickForm_automulti');
$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['autoselect'] = array('modules/Libs/QuickForm/FieldTypes/autoselect/autoselect.php','HTML_QuickForm_autoselect');
$GLOBALS['_HTML_QuickForm_registered_rules']['comparestring'] = array('HTML_QuickForm_Rule_CompareString', 'Rule/CompareString.php');

/**
 * This class provides saving any page as homepage for each user.
 */
class Libs_QuickForm extends Module {
	private $qf;
	
	public function construct($indicator = null, $action = '', $target = '', $on_submit = null, $form_name=null) {
		if (!$form_name)
			$form_name = 'libs_qf_'.md5($this->get_path());
		if($target=='' && $action!='')
			$target = '_blank';
		if(!isset($on_submit))
			$on_submit = $this->get_submit_form_js_by_name($form_name,true,$indicator,'')."return false;";
		$this->qf = new HTML_QuickForm($form_name, 'post', $action, $target, array('onSubmit'=>$on_submit), true);
		$this->qf->addElement('hidden', 'submited', 0);
		$this->qf->setRequiredNote('<span class="required_note_star">*</span> <span class="required_note">'.__('denotes required field').'</span>');
		eval_js_once("set_qf_sub0 = function(fn){var x=$(fn);if(x)x.submited.value=0}");
		eval_js("set_qf_sub0('".addslashes($form_name)."')");
		Base_ThemeCommon::load_css('Libs_QuickForm');
	}
	
	public function body($arg=null) {
		$this->qf->display($arg);
	}
	
	public function get_name() {
		$attrs = $this->qf->getAttributes();
		return $attrs['name'];
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
	
	public function accept(&$r) {
		$this->qf->accept($r);
	}
	
	public function & __call($func_name, array $args=array()) {
		if ($func_name=='addElement' && isset($args[0])) {
			if(is_string($args[0]))
				$type = $args[0];
			else
				$type = $args[0]->getType();
			if($type=='select' || $type=='commondata' || $type=='multiselect') {
				load_js('modules/Libs/QuickForm/select.js');
				if (!isset($args[4])) $args[4] = array('onkeydown'=>'typeAhead();');
				if (is_array($args[4])) $args[4]['onkeydown'] = 'typeAhead();';
				else $args[4] .= ' onkeydown="typeAhead();"';
			}
		}
		if (is_object($this->qf)) {
//			if($func_name==='accept') trigger_error(print_r($args,true));
			$return = call_user_func_array(array(& $this->qf, $func_name), $args);
		} else
			trigger_error("QuickFrom object doesn't exists", E_USER_ERROR);
		return $return;
	}
	
	public function get_submit_form_js($submited=true, $indicator=null, $queue=false) {
		if (!is_object($this->qf))
			throw new Exception("QuickFrom object doesn't exists");
		$form_name = $this->qf->getAttribute('name');
		return $this->get_submit_form_js_by_name($form_name,$submited,$indicator,$queue); 
	}
	public function get_submit_form_href($submited=true, $indicator=null) {
		 return ' href="javascript:void(0)" onClick="'.$this->get_submit_form_js($submited,$indicator).'" ';
	}

	public function get_submit_form_js_by_name($form_name, $submited, $indicator, $queue=false) {
		if (!is_array($form_name)) $form_name = array($form_name);
		if(!isset($indicator)) $indicator=__('Processing...');
		$fast = "+'&".http_build_query(array('__action_module__'=>$this->get_parent_path()))."'"; 
		$pre = '';
		$chj = '';
		$post = '';
		foreach ($form_name as $f) {
			if ($submited) $pre .= "$('".addslashes($f)."').submited.value=1;";
			$pre .= "Event.fire(document,'e:submit_form','".$f."');";
			$pre .= str_replace('this',"$('".addslashes($f)."')",Libs_QuickFormCommon::get_on_submit_actions());
			if ($chj) $chj .= "+'&'+";
			$chj .= "$('".addslashes($f)."').serialize()";
			if ($submited) $post .= "$('".addslashes($f)."').submited.value=0;";
		}
		$s = $pre."_chj(".$chj.$fast.",'".Epesi::escapeJS($indicator)."','".($queue?'queue':'')."');".$post;
		return $s;
	}

	public function assign_theme($name, & $theme, &$renderer=null){ 
		if(!isset($renderer)) $renderer = new HTML_QuickForm_Renderer_TCMSArraySmarty(); 
		$this->accept($renderer); 
		$form_data = $renderer->toArray();
		$theme->assign($name.'_name', $this->getAttribute('name')); 
		$theme->assign($name.'_data', $form_data);
		$theme->assign($name.'_open', $form_data['javascript'].'<form '.$form_data['attributes'].'>'.$form_data['hidden']."\n");
		$theme->assign($name.'_close', "</form>\n");
	}
	
	public function get_element_by_array(array $v, & $default_js = null) {
		$elem = null;
		if(!isset($v['param'])) $v['param']=null;
		if(!isset($v['values'])) $v['values']=null;
		switch($v['type']){
			case 'select':
				$elem = $this -> createElement('select',$v['name'],$v['label'],$v['values'],$v['param']);
				$default_js .= 'e = $(\''.$this->getAttribute('name').'\').'.$v['name'].';'.
				'for(i=0; i<e.length; i++) if(e.options[i].value==\''.$v['default'].'\'){e.options[i].selected=true;break;};';
				break;
			
			case 'static':
			case 'header':
				$elem = $this -> createElement($v['type'],isset($v['name'])?$v['name']:null,$v['label'],isset($v['values'])?$v['values']:'');
				break;
				
			case 'bool':
			case 'checkbox':
				$elem = $this -> createElement('checkbox',$v['name'],$v['label'],$v['values'],$v['param']);
				$default_js .= '$(\''.$this->getAttribute('name').'\').'.$v['name'].'.checked = '.($v['default']?1:0).';';
				break;
			
			case 'html':
                if(! isset($v['text'])) {
                    if(isset($v['label'])) {
                        $v['text'] = $v['label'];
                    } elseif(isset($v['name'])) {
                        $v['text'] = $v['name'];
                    } else {
                        trigger_error("Undefined index 'text' form 'html' field");
                    }
                }
				$elem = $this -> createElement($v['type'],$v['text']);
                break;

			case 'numeric':
				if(!isset($v['rule']) || !is_array($v['rule'])) $v['rule']=array();
				$v['type'] = 'text';
				$v['rule'][] = array('type'=>'numeric','message'=>__('This is not a valid number'));
			case 'password':
			case 'text':
			case 'hidden':
			case 'textarea':
				$elem = $this -> createElement($v['type'],$v['name'],$v['label'],$v['param']);
				$default_js .= '$(\''.$this->getAttribute('name').'\').'.$v['name'].'.value = \''.$v['default'].'\';';
				break;
						
			case 'callback':
				if(!isset($v['func']))
					trigger_error('Callback function not defined in '.$v['name'],E_USER_ERROR);
				$elem = call_user_func($v['func'],$v['name'],$v,$default_js);
				break;
			default:
				trigger_error('Invalid type: '.$v['type'],E_USER_ERROR);
		}
		if($this->isError($elem))
			trigger_error($elem->getMessage(),E_USER_ERROR);
		return $elem;
	}
	
	public function add_array($info, & $default_js=''){
		foreach($info as $v){
			if(!isset($v['param'])) $v['param']=null;
			if(!isset($v['values'])) $v['values']=null;
			switch($v['type']) {
				case 'radio':
					$radio = array();
					foreach($v['values'] as $k=>$x)
						$radio[] = $this -> createElement('radio',$v['name'],null,$x,$k,$v['param']);
					$this->addGroup($radio,null,$v['label']);
					$default_js .= 'e = $(\''.$this->getAttribute('name').'\').'.$v['name'].';'.
					'for(i=0; i<e.length; i++){e[i].checked=false;if(e[i].value==\''.$v['default'].'\')e[i].checked=true;};';
					break;
				case 'group':
					$elems = array();
					if(!isset($v['elems']))
						trigger_error('Empty group',E_USER_ERROR);
					foreach($v['elems'] as $x)
						$elems[] = $this->get_element_by_array($x,$default_js);
					$this->addGroup($elems,null,$v['label']);
					break;
				default:
					$this->qf->addElement($this->get_element_by_array($v,$default_js));
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
			
	public function add_table($table_name, array $cols, &$js='') { //TODO: add group here?
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
			if(isset($v['rule']['message']) && isset($v['rule']['type'])) $v['rule'] = array($v['rule']);
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
				$v['rule'][] = array('message'=>__('Text too long'), 'type'=>'maxlength', 'param'=>$meta->max_length);
				if(!isset($v['param'])) $v['param'] = array();
				if(is_string($v['param'])) $v['param'].=' maxlength=\''.$meta->max_length.'\'';
					else $v['param']['maxlength'] = $meta->max_length;
			}
			if($meta->not_null) {
				if(!isset($v['rule'])) $v['rule'] = array();
				$v['rule'][] = array('message'=>__('Field required'), 'type'=>'required');
			}
			$arr[] = $v;
		}
		$this->add_array($arr,$js);
	}
	
	public function add_error_closing_buttons() {
		$elements = array_keys($this->getSubmitValues());
		foreach ($elements as $e) {
			$err = $this->getElementError($e);
			if ($err) $this->setElementError($e, $err.' <a href="javascript:void(0);" onclick="this.parentNode.innerHTML=\'\'"><img src="'.Base_ThemeCommon::get_template_file('Libs_QuickForm','close.png').'"></a>');
		}
	}

	public function display_as_column() {
		$t = $this->init_module('Base_Theme');
		$this->add_error_closing_buttons();
		$this->assign_theme('form', $t);
		$t->display('column');
	}
	public function display_as_row() {
		$t = $this->init_module('Base_Theme');
		$this->add_error_closing_buttons();
		$this->assign_theme('form', $t);
		$t->display('row');
	}

}
?>
