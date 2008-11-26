<?php
/**
 * TCPDF class.
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-libs
 * @subpackage tcpdf
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_TCPDF extends Module {
	public $tcpdf;
	private static $lifetime = '-12 hours';
	private $lang;
	private $steps = array();
	private $pdf_ready = 0;
	private static $default_font = 'Helvetica';

	public function construct($orientation='P',$unit='mm',$format=null) {
		$this->lang = $this->init_module('Base/Lang');
		require_once('tcpdf4/tcpdf.php');
		
		if ($format===null) $format = Base_User_SettingsCommon::get('Libs/TCPDF','page_format');

		$this->tcpdf = new TCPDF($orientation, $unit, $format, true);

		$this->tcpdf->SetCreator(PDF_CREATOR);
		$this->tcpdf->SetAuthor("Powered by epesi");
		$this->tcpdf->SetKeywords("PDF");
				
		// set header and footer fonts
		$this->tcpdf->setHeaderFont(Array(self::$default_font, '', PDF_FONT_SIZE_MAIN));
		$this->tcpdf->setFooterFont(Array(self::$default_font, '', PDF_FONT_SIZE_DATA));
		
		//set margins
		$this->tcpdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
		$this->tcpdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$this->tcpdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		
		//set auto page breaks
		$this->tcpdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
		
		//set image scale factor
		$this->tcpdf->setImageScale(PDF_IMAGE_SCALE_RATIO); 
	}

	public function prepare_header() {
		foreach (array('title', 'subject') as $v)
			if (!isset($this->steps[$v])) trigger_error('PDF '.$v.' was not set, use $tcpdf->set_'.$v.'();',E_USER_ERROR);
		$logo_filename = Libs_TCPDFCommon::get_logo_filename();
		if (!file_exists($logo_filename)) $logo_filename = Base_ThemeCommon::get_template_file('Libs/TCPDF','logo-small.png'); 
		$this->tcpdf->SetHeaderData($logo_filename, PDF_HEADER_LOGO_WIDTH, $this->steps['title'], $this->steps['subject']);

		//set some language-dependent strings
		$l=array();
		$l['a_meta_charset'] = "UTF-8";
		$l['a_meta_dir'] = "ltr";
		$l['a_meta_language'] = "pl";
		
		$who = CRM_ContactsCommon::get_contact_by_user_id(Acl::get_user());
		if ($who!==null) $who = $who['last_name'].' '.$who['first_name'];
		else $who= Base_UserCommon::get_user_login(Acl::get_user());
		$when = date('Y-m-d H:i:s');
		$l['w_page'] = $this->lang->ht('Printed by %s, on %s, ',array($who,$when)).$this->lang->ht("Page");
		$this->tcpdf->setLanguageArray($l); 
		
		//initialize document
		$this->tcpdf->AliasNbPages();

		$this->SetFont(self::$default_font, '', 9);
	}

	public function stripHTML($html) {
		$html = str_replace(array("\n", "\t", "\r"), '', $html);
		$html = preg_replace('/\<\/?[aA][^\>]*\>/', '', $html);
		return $html;
	}

	public function writeHTML($html, $autobreak=true) {
		$html = $this->stripHTML($html);
		if ($autobreak) {
			$pages = $this->tcpdf->getNumPages();
			$tmppdf = clone($this->tcpdf);
			$this->tcpdf->WriteHTML($html,false,0,false);
			if ($pages!=$this->tcpdf->getNumPages()) {
				$this->tcpdf = $tmppdf;
				$this->tcpdf->AddPage();
				$this->tcpdf->WriteHTML($html,false,0,false);
			}
		} else
			$this->tcpdf->WriteHTML($html,false,0,false);
	}

	public function SetFont($family, $style='', $size=0) {
		$this->tcpdf->SetFont(self::$default_font, $style, $size);
	}

	public function & __call($func_name, array $args=array()) {
		if(is_callable(array(&$this->tcpdf,$func_name)))
			$ret = & call_user_func_array(array(&$this->tcpdf,$func_name), $args);
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
			Base_ActionBarCommon::add('save','Download PDF','href="'.$this->get_href($filename).'"');
		} else {
			Base_ActionBarCommon::add('print','Create PDF',$this->create_callback_href(array($this, 'start_preparing_pdf')));
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
		if($this->is_back()) $this->parent->reset();
		$form = & $this->init_module('Utils/FileUpload',array(false));
		$form->addElement('header', 'upload', $this->lang->t('Upload company logo'));
		$form->add_upload_element();
		$form->addElement('button',null,$this->lang->t('Upload'),$form->get_submit_form_href());
		$this->display_module($form, array( array($this,'upload_logo') ));
		Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
	}

	public function upload_logo($file,$oryg,$data) {
		$fp = fopen($file, "r");
		$ext = strrchr($oryg,'.');
		if($ext==='' || $ext!=='.png') {
			print($this->lang->t('Invalid extension. Only *.png is allowed.'));
			return;
		}
		$target_filename = Libs_TCPDFCommon::get_logo_filename();
		if (file_exists($target_filename)) unlink($target_filename);
		copy($file, $target_filename);
		print($this->lang->t('Upload successful.'));
	}
}

?>
