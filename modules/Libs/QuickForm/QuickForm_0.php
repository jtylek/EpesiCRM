<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-libs
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides saving any page as homepage for each user.
 * @package epesi-libs
 * @subpackage QuickForm
 */
class Libs_QuickForm extends Module {
	private $qf;
	
	public function construct($indicator = null, $action = '', $target = '', $on_submit = null) {
		$form_name = $this->parent->get_path();
		if($target=='' && $action!='')
			$target = '_blank';
		if(!isset($on_submit))
			$on_submit = $this->get_submit_form_js_by_name($form_name,true,$indicator)."return false;";
		$this->qf = new HTML_QuickForm($form_name, 'post', $action, $target, array('onSubmit'=>$on_submit), true);
		$this->qf->addElement('hidden', 'submited', 0);
		Base_ThemeCommon::load_css('Libs_QuickForm');
	}
	
	public function body($arg) {
		$this->qf->display($arg);
	}
	
	public function & __call($func_name, $args) {
		if (is_object($this->qf))
			$return = & call_user_func_array(array(&$this->qf, $func_name), $args);
		else
			trigger_error("QuickFrom object doesn't exists", E_USER_ERROR);
		return $return;
	}
	
	public function get_submit_form_js($submited=true, $indicator=null, $fast=true) {
		if (!is_object($this->qf))
			throw new Exception("QuickFrom object doesn't exists");
		$form_name = $this->qf->getAttribute('name');
		return $this->get_submit_form_js_by_name($form_name,$submited,$indicator,$fast); 
	}
	
	private function get_submit_form_js_by_name($form_name, $submited, $indicator) {
		global $base; 
		if(!isset($indicator)) $indicator='processing...';
        $fast = "+'&".str_replace('&amp;','&',http_build_query(array('__action_module__'=>$this->get_parent_path())))."'"; 
        $s = $base->run("process(".$base->get_client_id().",serialize_form('".addslashes($form_name)."')".$fast.")"); 
//	 	$s = $base->run("process(".$base->get_client_id().",serialize_form('".addslashes($form_name)."'))");
		$s = Libs_QuickFormCommon::get_on_submit_actions().'saja.updateIndicatorText(\''.addslashes($indicator).'\');'.$s;
		if($submited)	 	
	 		return "document.getElementById('".addslashes($form_name)."').submited.value=1;".$s."document.getElementById('".addslashes($form_name)."').submited.value=0;";
	 	else
	 		return $s; 
	}
}
?>
