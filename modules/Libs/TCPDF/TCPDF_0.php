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
 * @copyright Copyright &copy; 2006, Janusz Tylek
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
		$this->tcpdf->SetFont($family?$family:self::$default_font, $style, $size);
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
		return md5($this->get_path().'__'.Acl::get_user().'__'.(defined('CID') ? CID : false).'__'.session_id());
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
		return 'modules/Libs/TCPDF/download.php?'.http_build_query(array('id'=>defined('CID') ? CID : false,'pdf'=>$pdf_id,'filename'=>$dlfilename.'.pdf'));
	}
	
	public function admin() {
		if($this->is_back()) {
			$this->parent->reset();
			return;
		}
		$form = $this->init_module(Utils_FileUpload::module_name(),array(false));
		$form->addElement('header', 'upload', __('Upload company logo',array(),false));

		// Preview of whichever logo would actually render in a generated PDF
		// right now - mirrors TCPDFCommon::prepare_header()'s own fallback
		// logic exactly (custom logo if uploaded, else the built-in EPESI
		// logo), per Jasiek's request: previously a PDF could silently fall
		// back to the default logo with no way to see that from this screen.
		$custom_logo = Libs_TCPDFCommon::get_logo_filename();
		if (file_exists($custom_logo)) {
			$preview = $custom_logo.'?'.filemtime($custom_logo);
			$preview_caption = __('Current logo');
		} else {
			$preview = Base_ThemeCommon::get_template_file(Libs_TCPDF::module_name(),'logo-small.png');
			$preview_caption = __('Current logo (default EPESI logo - no custom logo uploaded)');
		}

		$form->add_upload_element();
		$form->addElement('button',null,__('Upload'),$form->get_submit_form_href().' class="submit btn btn-primary"');
		// column.tpl renders every button in its own trailing section, always
		// after the whole field grid and with nothing after it - so a
		// 'static' element (or plain print() once the card's already closed)
		// can't land below the button while staying inside the card. Capture
		// the rendered card HTML and splice the preview in just before its
		// own closing </div> instead.
		//
		// set_inline_display() is required for this to actually work:
		// display_module() normally prints just a placeholder <span> (real
		// content gets injected separately, out-of-band, later in the page
		// render) - get_html_of_module() only returns the real HTML directly
		// when the module is in inline-display mode (see Utils_RecordBrowser/
		// Reports/Reports_0.php's identical use on a GenericBrowser instance).
		// Without this, ob_get_clean() below only ever captures the
		// placeholder span, and the spliced-in preview ends up appended after
		// it - outside the card once the real content is injected.
		$form->set_inline_display();
		ob_start();
		$this->display_module($form, array( array($this,'upload_logo') ));
		$html = ob_get_clean();
		$preview_html = '<div>'.htmlspecialchars($preview_caption).'</div><img src="'.htmlspecialchars($preview).'" style="max-width:300px;" />';
		// Second-to-last </div>, not the last one: the last </div> closes
		// .card itself, but .card-body (one level in, where the padding
		// lives) closes just before that - landing inside .card but outside
		// .card-body put the preview inside the card's border/background yet
		// outside its padded content area, which read as "not really merged".
		$last = strrpos($html, '</div>');
		$pos = $last !== false ? strrpos(substr($html, 0, $last), '</div>') : false;
		print($pos !== false ? substr($html, 0, $pos).$preview_html.substr($html, $pos) : $html.$preview_html);
		Base_ActionBarCommon::add('delete', __('Delete logo'), $this->create_callback_href($this->delete_logo(...)));
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
	}

	public function upload_logo($file,$oryg,$data) {
		if (!$oryg) return;
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

	public function delete_logo() {
		@unlink(Libs_TCPDFCommon::get_logo_filename());
	}
}

?>
