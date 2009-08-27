<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage LeightboxPrompt
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_LeightboxPrompt extends Module {
	private $params = array();
	private $options = array();
	private $group = null;
	private $leightbox_ready = false;
	private $last_location = null;
	private $option_chosen = null;
	
	public function option_chosen($arg) {
		$this->option_chosen = $arg;
		return false;
	}
	
	public function construct() {
		$this->group = md5($this->get_path());
	}
	
	public function add_option($key, $label, $icon, $form=null) {
		$this->options[$key] = array('icon'=>$icon, 'form'=>$form, 'label'=>$label);
	}
	
	public function body($header='', $params = array()) {
		if (MOBILE_DEVICE) return;
		if (isset($_REQUEST['__location']) && $this->last_location!=$_REQUEST['__location']) {
			$this->last_location = $_REQUEST['__location'];
			$this->leightbox_ready = false;
		}
		if (!$this->leightbox_ready) {
			if (!empty($params)) {
				$this->params = $params;
				$js = 'f'.$this->group.'_set_params = function(arg'.implode(',arg',array_keys($params)).'){';
				foreach ($params as $k=>$v) {
					$js .= '$(\''.$this->group.'_'.$v.'\').value=arg'.$k.';';
				}
				$js .= '}';
				eval_js_once($js);
			}
			
			$this->leightbox_ready = true;

			eval_js_once('f'.$this->group.'_followups_deactivate = function(){leightbox_deactivate(\''.$this->group.'_followups_leightbox\');}');
			eval_js_once('f'.$this->group.'_show_form = function(arg){$(arg+\'_'.$this->group.'_form_section\').style.display=\'block\';$(\''.$this->group.'_buttons_section\').style.display=\'none\';}');
			eval_js('$(\''.$this->group.'_buttons_section\').style.display=\''.(count($this->options)==1?'none':'block').'\';');

			$buttons = array();
			$sections = array();
			foreach ($this->options as $k=>$v) {
				$next_button = array('icon'=>$v['icon'], 'label'=>$v['label']);
				if ($v['form']!==null) {
					static $adding_done = array();
					if (!isset($adding_done[$k])){
						$adding_done[$k] = true;
						if (!empty($params))
							foreach ($params as $w)
								$v['form']->addElement('hidden', $this->group.'_'.$w, $w, array('id'=>$this->group.'_'.$w));
					}
					$v['form']->addElement('button', 'cancel', $this->t('Cancel'), array('onclick'=>count($this->options)==1?'f'.$this->group.'_followups_deactivate();':'$(\''.$this->group.'_buttons_section\').style.display=\'block\';$(\''.$k.'_'.$this->group.'_form_section\').style.display=\'none\';'));
					$v['form']->addElement('submit', 'submit', $this->t('OK'), array('onclick'=>'f'.$this->group.'_followups_deactivate();'));
					ob_start();
					$th = $this->init_module('Base/Theme');
					$v['form']->assign_theme('form', $th);
					$th->display('form');
					$form_contents = ob_get_clean();
					$next_button['open'] = '<a href="javascript:void(0);" onclick="f'.$this->group.'_show_form(\''.$k.'\');">';
					$sections[] = '<div id="'.$k.'_'.$this->group.'_form_section" style="display:none;">'.$form_contents.'</div>'; 
					eval_js('$(\''.$k.'_'.$this->group.'_form_section\').style.display=\''.(count($this->options)!=1?'none':'block').'\';');
				} else {
					$next_button['open'] = '<a '.$this->create_callback_href(array($this,'option_chosen'), array($k)).' onmouseup="f'.$this->group.'_followups_deactivate();">';
				}
				$next_button['close'] = '</a>';
				$buttons[] = $next_button;
			}

			$theme = $this->init_module('Base/Theme');

			$theme->assign('open_buttons_section','<div id="'.$this->group.'_buttons_section">');
			$theme->assign('buttons',$buttons);
			$theme->assign('sections',$sections);
			$theme->assign('close_buttons_section','</div>');

			ob_start();
			$theme->display('leightbox');
			$profiles_out = ob_get_clean();
			Libs_LeightboxCommon::display($this->group.'_followups_leightbox', $profiles_out, $header, true);
		}
	}
	
	public function get_href($params=array()) {
		$ret = 'href="javascript:void(0)" class="lbOn" rel="'.$this->group.'_followups_leightbox"';
		if (!empty($params)) $ret .= ' onmousedown="f'.$this->group.'_set_params(\''.implode('\',\'',$params).'\');"';
		return $ret;
	}

	public function get_close_leightbox_href() {
		return 'href="javascript:void(0)" onclick="f'.$this->group.'_followups_deactivate();"';
	}

	public function export_values() {
		$ret = array();
		if ($this->option_chosen!==null) return array('option'=>$this->option_chosen);
		foreach ($this->options as $k=>$v) {
			if ($v['form']!==null && $v['form']->validate()) {
				$ret['option'] = $k;
				$vals = $v['form']->exportValues();
				if (is_array($this->params)) foreach ($this->params as $p) {
					$ret['params'][$p] = $vals[$this->group.'_'.$p];
					unset($vals[$this->group.'_'.$p]);
				}
				unset($vals['submit']);
				unset($vals['submited']);
				unset($vals['_qf__libs_qf_'.md5($v['form']->get_path())]); // TODO: not really nice
				$ret['form'] = $vals;
				break;	
			}
		}
		if (empty($ret)) return null; 
		return $ret;
	}
}

?>