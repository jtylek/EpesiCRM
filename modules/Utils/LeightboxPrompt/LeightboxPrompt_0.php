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
    private $params_list = array();
    private $options = array();
    private $group = null;
    private $leightbox_ready = false;
    private $last_location = null;
    private $selected_option = null;

    public function construct() {
        $this->group = md5($this->get_path());
    }
	
	public function get_group_key() {
		return $this->group;
	}

    public function add_option($key, $label, $icon, $form=null, $tooltip=null) {
    	$key = $key?: 'default';
    	
    	if ($tooltip && !is_array($tooltip))
    		$tooltip = [$tooltip];
    	
        $this->options[$key] = array('icon'=>$icon, 'form'=>$form, 'label'=>$label, 'tooltip'=>$tooltip);
        
        //calling open method causes lbp to not be opened by the links on the page in case of no form validation
        //this way works because no init is called
        if (isset($form) && $form->exportValue('submited') && !$form->validate()) Utils_LeightboxPromptCommon::open($this->group, $this->get_params([]));
    }
    
    public function add_options($options) {
    	foreach ($options as $option => $desc) {
    		$desc['label'] = $desc['label']?? $option;
    		
    		$desc['active'] = $desc['active']?? true;
    		
    		$desc['active'] = is_array($desc['active'])? $desc['active']: [$desc['active']];
    		
    		if (!(bool) array_product($desc['active'])) continue;
    		
    		$form = null;
    		if ($desc['elements']?? []) {
    			$form = $this->init_module(Libs_QuickForm::module_name());
    			
    			$elements = array_filter($desc['elements'], function($element) {
    				return $element['active']?? true;
    			});
    				
    			$form->add_array($elements);
    			
    			$form->setDefaults($desc['defaults']?? []);
    		}
    		
    		$this->add_option($option, $desc['label'], $desc['icon']?? null, $form, $desc['tip']?? null);
    	}
    }
    
    public function set_selected_option($option) {
    	$this->selected_option = $option;
    }

    public function body($header='', $params_list = array(), $additional_info='', $big=true) {
        if (MOBILE_DEVICE) return;
        if (isset($_REQUEST['__location']) && $this->last_location!=$_REQUEST['__location']) {
            $this->last_location = $_REQUEST['__location'];
            $this->leightbox_ready = false;
        }
        if (!$this->leightbox_ready) {
            $this->leightbox_ready = true;
            
            $this->params_list = $params_list;

            $active_option = $single_option = $this->get_single_option();
            
            $buttons = array();
            $sections = array();
            foreach ($this->options as $option_key=>$option) {
            	$next_button = array('icon'=>$option['icon'], 'label'=>$option['label'], 'tooltip' => $option['tooltip']);
                if ($option['form']!==null) $form = $option['form'];
                else $form = $this->options[$option_key]['form'] = $this->init_module(Libs_QuickForm::module_name());
                if (!empty($params_list)) {
                    foreach ($params_list as $param_key)
                        $form->addElement('hidden', $this->group.'_'.$param_key, 'none');
                }
                $tooltip = $this->button_tooltip_attrs($next_button);
                if ($option['form']!==null) {
                    $option['form']->addElement('button', 'cancel', __('Cancel'), array('id'=>$this->group.'_lp_cancel', 'onclick'=>$this->get_close_leightbox_href_js(!$single_option)));
                    $option['form']->addElement('submit', 'submit', __('OK'), array('id'=>$this->group.'_lp_submit', 'onclick'=>$this->get_close_leightbox_href_js()));
                    ob_start();
                    $th = $this->init_module(Base_Theme::module_name());
                    $option['form']->assign_theme('form', $th);
                    $th->assign('id', $this->get_instance_id());
                    $th->display('form');
                    $form_contents = ob_get_clean();
                    
                    $next_button['open'] = '<a ' . $this->get_form_show_href($option_key) . ' ' . $tooltip . '>';
                    $sections[] = '<div id="'.$this->group.'_'.$option_key.'_form_section" class="'.$this->group.'_form_section" style="display:none;">'.$form_contents.'</div>';
                    if ($this->selected_option ===  $option_key)
                    	$active_option = $option_key; // open this selection if selected_option set
                    
                    if ($option['form']->exportValue('submited') && !$option['form']->validate())						
						$active_option = $option_key; // open this selection if form submitted but not valid
                    
                } else {
                    $next_button['open'] = '<a href="javascript:void(0);" onmouseup="' . $this->get_close_leightbox_href_js() . $form->get_submit_form_js() . ';" ' . $tooltip . '>';
                    $form->display();
                }
                $next_button['close'] = '</a>';
                $buttons[] = $next_button;
            }
            
            $active_option = $active_option?: '';
			
            load_js($this->get_module_dir() . 'js/leightbox_prompt.js');
            load_js($this->get_module_dir() . 'js/jquery-deparam.js');
            eval_js('Utils_LeightboxPrompt.init("' . $this->group . '", "' . $active_option . '");');           
            
            $theme = $this->init_module(Base_Theme::module_name());

            $theme->assign('open_buttons_section','<div id="'.$this->group.'_buttons_section">');
            $theme->assign('buttons',$buttons);
            $theme->assign('sections',$sections);
            $theme->assign('additional_info',$additional_info);
            $theme->assign('close_buttons_section','</div>');

            ob_start();
            $theme->display('leightbox');
            $profiles_out = ob_get_clean();            

            Libs_LeightboxCommon::display($this->group.'_prompt_leightbox', $profiles_out, $header, $big);
        }
    }
    
    private function button_tooltip_attrs($button) {
    	$ret = '';
    	if ($button['tooltip']) {
    		if (is_callable($button['tooltip'][0]))
    			$ret = call_user_func_array([Utils_TooltipCommon::class, 'ajax_open_tag_attrs'], $button['tooltip']);
    		else
    			$ret = call_user_func_array([Utils_TooltipCommon::class, 'open_tag_attrs'], $button['tooltip']);
    	}
    	return $ret;
    }
    
    private function get_single_option() {
    	if (count($this->options) == 1) {    	
	    	$option_keys = array_keys($this->options);
	    	
	    	return reset($option_keys);	    	
	    }
    	return false;
    }
    
    private function get_form_show_href($option_key) {
    	return 'href="javascript:void(0);" onclick="'.$this->get_form_show_href_js($option_key).'"';
    }
    
    private function get_form_show_href_js($option_key) {
    	return 'Utils_LeightboxPrompt.show_form(\''.$this->group.'\', \''.$option_key.'\');';
    }
    
    private function get_params($params) {
    	if (empty($params)) return array();
    	
    	$ret = $params;
    	if (count($this->params_list) != count(array_intersect($this->params_list, array_keys($params)))) {
    		$ret = array_combine($this->params_list, $params);
    	}

    	return $ret;
    }

    public function get_href($params=array()) {
		return Utils_LeightboxPromptCommon::get_href($this->group, $this->get_params($params));
    }

    public function open($params=array()) {
    	$this->init_leightbox();
    	
		return Utils_LeightboxPromptCommon::open($this->group, $this->get_params($params));
    }

    private $init = false;
    public function get_href_js($params=array()) {
		$this->init_leightbox();
		
        return Utils_LeightboxPromptCommon::get_open_js($this->group, $this->get_params($params));
    }
	
	public function init_leightbox() {
        if (!$this->init) print('<a style="display:none;" '.$this->get_href().'></a>');
        $this->init=true;
	}
	
	public function get_options_count() {
        return count($this->options);
	}

    public function get_close_leightbox_href($reset_view = false) {
        return 'href="javascript:void(0)" onclick="' . $this->get_close_leightbox_href_js($reset_view) . '"';
    }
    
    public function get_close_leightbox_href_js($reset_view = false) {
    	return 'Utils_LeightboxPrompt.deactivate(\''.$this->group.'\', ' . ($reset_view?1:0) . ');';
    }

    public function export_values() {
        $ret = array();
        foreach ($this->options as $option_key=>$option) {
            if ($option['form']!==null && $option['form']->validate()) {
                $ret['option'] = $option_key;
                $vals = array_merge($option['form']->exportValues(), Utils_FileUpload_Dropzone::export_values($option['form']));
                if (is_array($this->params_list)) foreach ($this->params_list as $p) {
                    $ret['params'][$p] = $vals[$this->group.'_'.$p];
                    unset($vals[$this->group.'_'.$p]);
                }
                unset($vals['submit']);
                unset($vals['submited']);
                unset($vals['_qf__libs_qf_'.md5($option['form']->get_path())]); // TODO: not really nice
                $ret['form'] = $vals;
                break;
            }
        }
        if (empty($ret)) return null;
        return $ret;
    }
}

?>
