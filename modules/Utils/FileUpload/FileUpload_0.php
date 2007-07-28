<?php
/**
 * Uploads file
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Main class
 * @package epesi-utils
 * @subpackage file-uploader
 */
class Utils_FileUpload extends Module {
	private $on_submit = null;
	private $form = null;

	public function construct() {
		$this->lang = & $this->init_module('Base/Lang');
		$this->form = & $this->init_module('Libs/QuickForm', array($this->lang->ht('Uploading file...'),'modules/Utils/FileUpload/upload.php','upload_iframe',''),'file_chooser');
		$this->form->addElement('static',null,null,'<iframe frameborder="0" id="upload_iframe", name="upload_iframe" src="" scrolling="No" height="0" width="0"></iframe>');
	}

	public function addElement() {
		$arr = func_get_args();
		if($arr[0]=='submit') trigger_error('Unable to add submit element to Utils/FileUpload',E_USER_ERROR);
		return call_user_func_array(array($this->form,'addElement'),$arr);
	}
	
	public static function createElement() {
		$arr = func_get_args();
		if($arr[0]=='submit') trigger_error('Unable to add submit element to Utils/FileUpload',E_USER_ERROR);
		return call_user_func_array(array('HTML_QuickForm','createElement'),$arr);
	}
	
	public function addRule() {
		$arr = func_get_args();
		return call_user_func_array(array($this->form,'addRule'),$arr);
	}

	public function set_submit_callback($sub) {
		$this->on_submit = $sub;
	}
	
	public function body($on_sub) {
		if(isset($on_sub)) $this->on_submit = $on_sub;
		if(!isset($this->on_submit)) trigger_error('You have to specify "on submit" method',E_USER_ERROR);
		
		$this->form->addElement('hidden','uploaded_file');
		$this->form->addElement('hidden','original_file');
		$form_name = $this->form->getAttribute('name');
		$this->form->addElement('hidden','form_name',$form_name);

		$s = $this->form->get_submit_form_js(false,$this->lang->ht('Processing file...'));
		$s = str_replace("saja.","parent.saja.",$s);
		$s = str_replace("serialize_form","parent.serialize_form",$s);

		$this->form->addElement('hidden','submit_js',$s);
		$this->form->addElement('file', 'file', $this->lang->ht('Specify file'));
		$this->form->addElement('static',null,$this->lang->t('Upload status'),'<div id="upload_status"></div>');
		$this->form->addElement('submit', 'button', $this->lang->ht('Upload'), "onClick=\"document.getElementById('upload_status').innerHTML='uploading...'; submit(); disabled=true;\"");
		
		if($this->form->validate()) {
			$this->form->process(array($this,'submit_parent'));
			//cleanup all unnecessary tmp files
/*			$dd = $this->get_data_dir();
			$ls = scandir($dd);
			$rt = microtime(true);
			foreach($ls as $file) {
				$reqs = array();
				if(!eregi('^tmp_([0-9]+).([0-9]+)$',$file, $reqs)) continue;
				$rtc = $reqs[1].'.'.$reqs[2];
				if(floatval($rt)-floatval($rtc)>ini_get("session.gc_maxlifetime")) //files older then session lifetime
					unlink($dd.'/'.$file);
			}*/
		} else
			$this->form->display();
	}
	
	public function submit_parent($data) {
		if(call_user_func($this->on_submit, $data['uploaded_file'], $data['original_file'], $data))
			location(array());
		unlink($data['uploaded_file']);
	}
}

?>