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
	
	public function get_group_key() {
		return $this->group;
	}

    public function add_option($key, $label, $icon, $form=null) {
        $this->options[$key] = array('icon'=>$icon, 'form'=>$form, 'label'=>$label);
    }

    public function body($header='', $params = array(), $add_disp='', $big=true) {
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
                    $js .= 'els=document.getElementsByName(\''.$this->group.'_'.$v.'\');';
                    $js .= 'i=0;while(i<els.length){els[i].value=arg'.$k.';i++;}';
                }
                $js .= '}';
                eval_js($js);
            }

            $this->leightbox_ready = true;

            eval_js_once('f'.$this->group.'_prompt_deactivate = function(){leightbox_deactivate(\''.$this->group.'_prompt_leightbox\');}');
            eval_js_once('f'.$this->group.'_show_form = function(arg){$(arg+\'_'.$this->group.'_form_section\').style.display=\'block\';$(\''.$this->group.'_buttons_section\').style.display=\'none\';}');
            eval_js('$(\''.$this->group.'_buttons_section\').style.display=\''.(count($this->options)==1?'none':'block').'\';');

            $buttons = array();
            $sections = array();
            foreach ($this->options as $k=>$v) {
                $next_button = array('icon'=>$v['icon'], 'label'=>$v['label']);
                if ($v['form']!==null) $form = $v['form'];
                else $form = $this->options[$k]['form'] = $this->init_module('Libs/QuickForm');
                if (!empty($params)) {
                    foreach ($params as $w)
                        $form->addElement('hidden', $this->group.'_'.$w, 'none', array('id'=>$this->group.'_'.$w));
                }
                if ($v['form']!==null) {
                    $v['form']->addElement('button', 'cancel', __('Cancel'), array('id'=>$this->group.'_lp_cancel', 'onclick'=>count($this->options)==1?'f'.$this->group.'_prompt_deactivate();':'$(\''.$this->group.'_buttons_section\').style.display=\'block\';$(\''.$k.'_'.$this->group.'_form_section\').style.display=\'none\';'));
                    $v['form']->addElement('submit', 'submit', __('OK'), array('id'=>$this->group.'_lp_submit', 'onclick'=>'f'.$this->group.'_prompt_deactivate();'));
                    ob_start();
                    $th = $this->init_module('Base/Theme');
                    $v['form']->assign_theme('form', $th);
                    $th->display('form');
                    $form_contents = ob_get_clean();
                    $next_button['open'] = '<a href="javascript:void(0);" onclick="f'.$this->group.'_show_form(\''.$k.'\');">';
                    $sections[] = '<div id="'.$k.'_'.$this->group.'_form_section" style="display:none;">'.$form_contents.'</div>';
                    eval_js('$(\''.$k.'_'.$this->group.'_form_section\').style.display=\''.(count($this->options)!=1?'none':'block').'\';');
                } else {
//                  $next_button['open'] = '<a '.$this->create_callback_href(array($this,'option_chosen'), array($k)).' onmouseup="f'.$this->group.'_prompt_deactivate();">';
                    $next_button['open'] = '<a href="javascript:void(0);" onmouseup="f'.$this->group.'_prompt_deactivate();'.$form->get_submit_form_js().';">';
                    $form->display();
                }
                $next_button['close'] = '</a>';
                $buttons[] = $next_button;
            }

            $theme = $this->init_module('Base/Theme');

            $theme->assign('open_buttons_section','<div id="'.$this->group.'_buttons_section">');
            $theme->assign('buttons',$buttons);
            $theme->assign('sections',$sections);
            $theme->assign('additional_info',$add_disp);
            $theme->assign('close_buttons_section','</div>');

            ob_start();
            $theme->display('leightbox');
            $profiles_out = ob_get_clean();
            Libs_LeightboxCommon::display($this->group.'_prompt_leightbox', $profiles_out, $header, $big);
        }
    }

    public function get_href($params=array()) {
		return Utils_LeightboxPromptCommon::get_href($this->group, $params);
    }

    public function open($params=array()) {
		return Utils_LeightboxPromptCommon::open($this->group, $params);
    }

    private $init = false;
    public function get_href_js($params=array()) {
		$this->init_leightbox();
        $ret = 'leightbox_activate(\''.$this->group.'_prompt_leightbox\');';
        if (!empty($params)) $ret .= 'f'.$this->group.'_set_params(\''.implode('\',\'',$params).'\');';
        return $ret;
    }
	
	public function init_leightbox() {
        if (!$this->init) print('<a style="display:none;" '.$this->get_href().'></a>');
        $this->init=true;
	}

    public function get_close_leightbox_href() {
        return 'href="javascript:void(0)" onclick="f'.$this->group.'_prompt_deactivate();"';
    }

    public function export_values() {
        $ret = array();
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
