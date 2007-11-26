<?php
/**
 * Uploads file
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-utils
 * @subpackage file-uploader
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_FileUpload extends Module {
	private $on_submit = null;
	private $form = null;
	private $upload_button_caption = 'Upload';

	/**
	 * Module constructor.
	 */
	public function construct($req=true) {
		$this->lang = & $this->init_module('Base/Lang');
		$this->form = & $this->init_module('Libs/QuickForm', array($this->lang->ht('Uploading file...'),'modules/Utils/FileUpload/upload.php','upload_iframe',''),'file_chooser');
		$this->form->addElement('static',null,null,'<iframe frameborder="0" id="upload_iframe", name="upload_iframe" src="" scrolling="No" height="0" width="0"></iframe>');
		$this->form->addElement('hidden','required',$req?'1':'0');
	}

	/**
	 * Calls QuickForm method addElement() on own QuickForm object.
	 *
	 * @param mixed refer to QuickForm addElement() method
	 */
	public function addElement() {
		$arr = func_get_args();
		if($arr[0]=='submit') trigger_error('Unable to add submit element to Utils/FileUpload',E_USER_ERROR);
		return call_user_func_array(array($this->form,'addElement'),$arr);
	}

	/**
	 * Calls QuickForm method createElement() on own QuickForm object.
	 *
	 * @param mixed refer to QuickForm createElement() method
	 */
	public static function createElement() {
		$arr = func_get_args();
		if($arr[0]=='submit') trigger_error('Unable to add submit element to Utils/FileUpload',E_USER_ERROR);
		return call_user_func_array(array('HTML_QuickForm','createElement'),$arr);
	}

	/**
	 * Calls QuickForm method addRule() on own QuickForm object.
	 *
	 * @param mixed refer to QuickForm addRule() method
	 */
	public function addRule() {
		$arr = func_get_args();
		return call_user_func_array(array($this->form,'addRule'),$arr);
	}

	/**
	 * Assigns method to submit action.
	 * This method will recieve three arguments:
	 * file - filename of newly uploaded image
	 * name - filename of the original file
	 * data - QuickForm data
	 *
	 * @param method method for submit action
	 */
	public function set_submit_callback($sub) {
		$this->on_submit = $sub;
	}

	/**
	 * Upload button caption
	 *
	 * @param string
	 */
	public function set_upload_button_caption($x) {
		$this->upload_button_caption = $x;
	}

	/**
	 * Displays the form.
	 *
	 * @param method method for submit action
	 */
	public function body($on_sub) {
		if(isset($on_sub)) $this->on_submit = $on_sub;
		if(!isset($this->on_submit)) trigger_error('You have to specify "on submit" method',E_USER_ERROR);

		$this->form->addElement('hidden','uploaded_file');
		$this->form->addElement('hidden','original_file');
		$form_name = $this->form->getAttribute('name');
		$this->form->addElement('hidden','form_name',$form_name);

		$s = $this->form->get_submit_form_js(false,$this->lang->ht('Processing file...'));
		$s = str_replace("Epesi.","parent.Epesi.",$s);
		$s = str_replace('$','parent.$',$s);

		$this->form->addElement('hidden','submit_js',$s);
		$this->form->addElement('file', 'file', $this->lang->ht('Specify file'));
		$this->form->addElement('static',null,$this->lang->t('Upload status'),'<div id="upload_status"></div>');
		$this->form->addElement('submit', 'button', $this->lang->ht($this->upload_button_caption), "onClick=\"$('upload_status').innerHTML='uploading...'; submit(); disabled=true;\"");

		if($this->form->validate()) {
			$this->form->process(array($this,'submit_parent'));
		} else
			$this->form->display();
	}

	/**
	 * For internal use only.
	 */
	public function submit_parent($data) {
		if(call_user_func($this->on_submit, $data['uploaded_file'], $data['original_file'], $data))
			location(array());
		@unlink($data['uploaded_file']);
	}
}

?>
