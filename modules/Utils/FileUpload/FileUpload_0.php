<?php
/**
 * Uploads file
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage file-uploader
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_FileUpload extends Module {
	private $on_submit = null;
	private $on_submit_args = array();
	private $form = null;
	private $upload_button_caption;
	private $submit_button = true;

	/**
	 * Module constructor.
	 */
	public function construct($req=true) {
		$this->added_upload_elem = false;
		$this->upload_button_caption = __('Upload');
		$this->form = $this->init_module('Libs/QuickForm', array(__('Uploading file...'),'modules/Utils/FileUpload/upload.php','upload_iframe',''),'file_chooser');
		$this->form->addElement('static','upload_iframe',null,'<iframe frameborder="0" id="upload_iframe" name="upload_iframe" src="" style="display:none"></iframe>');
		$this->form->addElement('hidden','required',$req?'1':'0');
		$this->form->addElement('hidden','cid',CID);
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
	public function & __call($func_name, array $args=array()) {
		if (is_object($this->form))
			$return = call_user_func_array(array(& $this->form, $func_name), $args);
		else
			trigger_error("QuickFrom object doesn't exists", E_USER_ERROR);
		return $return;
	}

	public function accept(&$r) {
		$this->form->accept($r);
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
		$args = func_get_args();
		array_shift($args);
		$this->on_submit_args = $args;
	}

	/**
	 * Upload button caption
	 *
	 * @param string
	 */
	public function set_upload_button_caption($x) {
		$this->upload_button_caption = $x;
	}
	
	public function set_max_file_size($s) {
		if(!is_numeric($s)) trigger_error('Invalid file size limit: '.$s,E_USER_ERROR);
		$this->form->addElement('hidden','MAX_FILE_SIZE',$s);
	}

	public function add_upload_element($label=null) {
		if($this->added_upload_elem) return;
		$this->added_upload_elem = true;

		$form_name = $this->form->getAttribute('name');
		$this->form->addElement('hidden','form_name',$form_name);

		$s = $this->form->get_submit_form_js(false,__('Processing file...'));

		$this->form->addElement('hidden','submit_js',$s);
		$this->form->addElement('file', 'file', $label?$label:__('Specify file'));
	}

	public function get_submit_form_js() {
		$this->submit_button=false;
		$form_name = $this->form->getAttribute('name');
		return "if(Epesi.procOn>0)return false;Epesi.updateIndicatorText('".__('Uploading...')."');Epesi.procOn++;Epesi.updateIndicator();document.forms['".$this->form->getAttribute('name')."'].submit();";
	}

	public function get_submit_form_href() {
		return " onClick=\"".$this->get_submit_form_js()."\" href=\"javascript:void(0)\" ";
	}

	/**
	 * Displays the form.
	 *
	 * @param method method for submit action
	 */
	public function body($on_sub = null) {
		if(isset($on_sub)) {
			$args = func_get_args();
			call_user_func_array(array($this,'set_submit_callback'),$args);
		}
		if(!isset($this->on_submit)) trigger_error('You have to specify "on submit" method',E_USER_ERROR);

		$this->add_upload_element();

		if($this->submit_button)
			$this->form->addElement('submit', 'button', $this->upload_button_caption, $this->get_submit_form_href());

		if($this->form->validate()) {
			$this->form->process(array($this,'submit_parent'));
		} else
			$this->form->display();
	}

	/**
	 * For internal use only.
	 */
	public function submit_parent($data) {
/*		error_log(date('Y-m-d H:i:s')."\n",3,'data/file_upload_err.txt');
		if(!isset($_SESSION['client']['uploaded_file']) || !isset($_SESSION['client']['uploaded_original_file'])) {
			E2::alert('Invalid upload - session expired?');
			error_log(date('Y-m-d H:i:s').' '.print_r($_SESSION['client'],true)."\n\n\n",3,'data/file_upload_err.txt');
			return;
		}*/
		if(call_user_func_array($this->on_submit, array_merge(array($_SESSION['client']['uploaded_file'], $_SESSION['client']['uploaded_original_file'], $data),$this->on_submit_args)))
			location(array());
		@unlink($_SESSION['client']['uploaded_file']);
		unset($_SESSION['client']['uploaded_file']);
		unset($_SESSION['client']['uploaded_original_file']);
	}
	
	public function is_file() {
		return isset($_SESSION['client']['uploaded_file']) && $_SESSION['client']['uploaded_file'];
	}
}

?>
