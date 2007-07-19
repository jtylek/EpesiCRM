<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_FileUpload extends Module {
	private $form_header = null;
	private $on_submit = null;

	public function set_header($text) {
		$this->form_header = $text;
	}

	public function set_submit_callback($sub) {
		$this->on_submit = $sub;
	}
	
	public function body($on_sub,$header) {
		if(isset($header)) $this->form_header = $header;
		if(isset($on_sub)) $this->on_submit = $on_sub;
		if(!isset($this->on_submit)) trigger_error('You have to specify "on submit" method',E_USER_ERROR);
		
		$this->lang = & $this->init_module('Base/Lang');
		$f = & $this->init_module('Libs/QuickForm', array($this->lang->ht('Uploading file...'),'modules/Utils/FileUpload/upload.php','upload_iframe',''),'file_chooser');
		$f->addElement('static',null,null,'<iframe frameborder="0" id="upload_iframe", name="upload_iframe" src="" scrolling="No" height="0" width="0"></iframe>');
		$f->addElement('hidden','uploaded_file');
		$f->addElement('hidden','original_file');
		if(isset($this->form_header))
			$f->addElement('header',null,$this->form_header);
		$form_name = $f->getAttribute('name');
		$f->addElement('hidden','form_name',$form_name);

		$s = $f->get_submit_form_js(false,$this->lang->ht('Processing file...'));
		$s = str_replace("saja.","parent.saja.",$s);
		$s = str_replace("serialize_form","parent.serialize_form",$s);

		$f->addElement('hidden','submit_js',$s);
		$f->addElement('file', 'file', $this->lang->ht('Specify file'));
		$f->addElement('static',null,$this->lang->t('Upload status'),'<div id="upload_status"></div>');
		$f->addElement('submit', 'button', $this->lang->ht('Upload'), "onClick=\"document.getElementById('upload_status').innerHTML='uploading...'; submit(); disabled=true;\"");
		
		if($f->validate()) {
			if(call_user_func($this->on_submit,$f->exportValue('uploaded_file'),$f->exportValue('original_file')))
				location(array());
			//cleanup all unnecessary tmp files
			$dd = $this->get_data_dir();
			$ls = scandir($dd);
			$rt=microtime(true);
			foreach($ls as $file) {
				$reqs = array();
				if(!eregi('^tmp_([0-9]+).([0-9]+)$',$file, $reqs)) continue;
				$rtc = $reqs[1].'.'.$reqs[2];
				if(floatval($rt)-floatval($rtc)>86400) //files older then 24h
					unlink($dd.'/'.$file);
			}
		} else
			$f->display();
	}
}

?>