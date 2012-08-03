<?php
/**
 * TCPDF class.
 *
 * This module uses TCPDF PHP class released under
 * GNU LESSER GENERAL PUBLIC LICENSE Version 2.1
 * Author: Nicola Asuni 
 * Copyright (c) 2001-2008: Nicola Asuni
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage tcpdf
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_TCPDF extends Module {
	public static $default_font = 'dejavusanscondensed';//'Helvetica';
	public $tcpdf;
	private static $lifetime = '-12 hours';
	private $steps = array();
	private $pdf_ready = 0;

	public function construct($orientation='P',$unit='mm',$format=null) {
		$this->tcpdf = Libs_TCPDFCommon::new_pdf($orientation,$unit,$format);
	}

	public function prepare_header() {
		foreach (array('title', 'subject') as $v)
			if (!isset($this->steps[$v])) trigger_error('PDF '.$v.' was not set, use $tcpdf->set_'.$v.'();',E_USER_ERROR);
			
		Libs_TCPDFCommon::prepare_header($this->tcpdf, $this->steps['title'], $this->steps['subject']);
	}

	public function writeHTML($html, $autobreak=true) {
		Libs_TCPDFCommon::writeHTML($this->tcpdf, $html, $autobreak);
	}

	public function SetFont($family, $style='', $size=0) {
		$this->tcpdf->SetFont(self::$default_font, $style, $size);
	}

	public function & __call($func_name, array $args=array()) {
		if(is_callable(array(&$this->tcpdf,$func_name)))
			$ret = call_user_func_array(array(&$this->tcpdf,$func_name), $args);
		else
			$ret = false;
		return $ret;
	}

	public function set_title($str) {
		$this->steps['title'] = $str;
		$this->tcpdf->SetTitle($str);		
	}

	public function set_subject($str) {
		$this->steps['subject'] = $str;
		$this->tcpdf->SetSubject($str);
	}

	public function clean_up_old_pdfs() {
		$time = date('Y-m-d H:i:s', strtotime(self::$lifetime));
		$ret = DB::Execute('SELECT filename FROM libs_tcpdf_pdf_index WHERE created_on<%T', array($time));
		while ($row = $ret->FetchRow()) {
			$fn = $this->full_path($row['filename']);
			if (file_exists($fn)) unlink($fn);
		}
		$ret = DB::Execute('DELETE FROM libs_tcpdf_pdf_index WHERE created_on<%T', array($time));
	}
	
	public function generate_name() {
		return md5($this->get_path().'__'.Acl::get_user().'__'.CID.'__'.session_id());
	}
	
	public function full_path($filename) {
		return $this->get_data_dir().$filename.'.pdf';
	}
	
	public function start_preparing_pdf() {
		$this->pdf_ready = 1;
		return false;
	}

	public function prepare() {
		return $this->pdf_ready;
	}
	
	public function body() {
	}
		
	public function add_actionbar_icon($filename) {
		if ($this->pdf_ready){
			Base_ActionBarCommon::add('save',__('Download PDF'),'target="_blank" href="'.$this->get_href($filename).'"');
		} else {
			Base_ActionBarCommon::add('print',__('Create PDF'),$this->create_callback_href(array($this, 'start_preparing_pdf')));
		}
	}
	
	public function action_bar_icon() {
	}

	public function get_href($dlfilename=null) {
		$this->clean_up_old_pdfs();

		$pdf_id = $this->get_path();
		$s = $this->tcpdf->Output('','S');
		$filename = $this->generate_name();
		DB::Execute('INSERT INTO libs_tcpdf_pdf_index (created_on, filename) VALUES (%T, %s)', array(date('Y-m-d H:i:s'),$filename));
		$filename = $this->full_path($filename);
		file_put_contents($filename, $s);
		$this->set_module_variable('pdf', $filename);
		if(!isset($dlfilename)) $dlfilename='download';
		$this->tcpdf = null;
		return 'modules/Libs/TCPDF/download.php?'.http_build_query(array('id'=>CID,'pdf'=>$pdf_id,'filename'=>$dlfilename.'.pdf'));
	}
	
	public function admin() {
		if($this->is_back()) {
			$this->parent->reset();
			return;
		}
		$form = $this->init_module('Utils/FileUpload',array(false));
		$form->addElement('header', 'upload', __('Upload company logo',array(),false));
		$form->add_upload_element();
		$form->addElement('button',null,__('Upload'),$form->get_submit_form_href());
		$this->display_module($form, array( array($this,'upload_logo') ));
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
	}

	public function upload_logo($file,$oryg,$data) {
		$fp = fopen($file, "r");
		$ext = strrchr($oryg,'.');
		if($ext==='' || $ext!=='.png') {
			print(__('Invalid extension. Only *.png is allowed.',array(),false));
			return;
		}
		$target_filename = Libs_TCPDFCommon::get_logo_filename();
		if (file_exists($target_filename)) unlink($target_filename);
		copy($file, $target_filename);
		print(__('Upload successful.',array(),false));
	}
}

?>
