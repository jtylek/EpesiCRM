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

define('TCPDF_DIR', 'modules/Libs/TCPDF/tcpdf4/');

class Libs_TCPDFCommon extends ModuleCommon {
	private static $default_font = 'dejavusanscondensed';//'Helvetica';

	public static function user_settings(){
		return array('Printing settings'=>array(
			array('name'=>'page_format','label'=>'Page format','type'=>'select','values'=>array('A4'=>'A4','LETTER'=>'LETTER','LEGAL'=>'LEGAL'),'default'=>'LETTER')
			));
	}

	public function new_pdf($orientation='P',$unit='mm',$format=null) {
		require_once(TCPDF_DIR.'tcpdf.php');
		
		if ($format===null) $format = Base_User_SettingsCommon::get('Libs/TCPDF','page_format');

		$tcpdf = new TCPDF($orientation, $unit, $format, true);

		$tcpdf->SetCreator(PDF_CREATOR);
		$tcpdf->SetAuthor("Powered by epesi");
		$tcpdf->SetKeywords("PDF");
				
		// set header and footer fonts
		$tcpdf->setHeaderFont(Array(self::$default_font, '', PDF_FONT_SIZE_MAIN));
		$tcpdf->setFooterFont(Array(self::$default_font, '', PDF_FONT_SIZE_DATA));
		
		//set margins
		$tcpdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
		$tcpdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$tcpdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		
		//set auto page breaks
		$tcpdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
		
		//set image scale factor
		$tcpdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
		
		return $tcpdf; 
	}

	public function prepare_header(& $tcpdf, $title='', $subject='', $printed_by=true, $logo_filename=null) {
		if ($logo_filename===null) $logo_filename = Libs_TCPDFCommon::get_logo_filename();
		if (!file_exists($logo_filename)) $logo_filename = Base_ThemeCommon::get_template_file('Libs/TCPDF','logo-small.png'); 
		if ($title!==null) $tcpdf->SetHeaderData($logo_filename, PDF_HEADER_LOGO_WIDTH, $title, $subject);

		//set some language-dependent strings
		$l=array();
		$l['a_meta_charset'] = "UTF-8";
		$l['a_meta_dir'] = "ltr";
		$l['a_meta_language'] = "pl";
		
		$who = CRM_ContactsCommon::get_contact_by_user_id(Acl::get_user());
		if ($who!==null) $who = $who['last_name'].' '.$who['first_name'];
		else $who= Base_UserCommon::get_user_login(Acl::get_user());
		$when = date('Y-m-d H:i:s');
		$l['w_page'] = '';
		if ($printed_by) $l['w_page'] .= Base_LangCommon::ts('Libs_TCPDF','Printed by %s, on %s, ',array($who,$when));
		$l['w_page'] .= Base_LangCommon::ts('Libs_TCPDF','Page');
		$tcpdf->setLanguageArray($l); 
		
		//initialize document
		$tcpdf->AliasNbPages();

		self::SetFont($tcpdf, self::$default_font, '', 9);
	}

	public function add_page(& $tcpdf) {
		$tcpdf->AddPage();
	}

	public function writeHTML(& $tcpdf, $html, $autobreak=true) {
		$html = Libs_TCPDFCommon::stripHTML($html);
		if ($autobreak) {
			$pages = $tcpdf->getNumPages();
			$tmppdf = clone($tcpdf);
			$tcpdf->WriteHTML($html,false,0,false);
			if ($pages!=$tcpdf->getNumPages()) {
				$tcpdf = $tmppdf;
				$tcpdf->AddPage();
				$tcpdf->WriteHTML($html,false,0,false);
			}
		} else
			$tcpdf->WriteHTML($html,false,0,false);
	}

	public function SetFont(& $tcpdf, $family, $style='', $size=0) {
		$tcpdf->SetFont(self::$default_font, $style, $size);
	}
	
	public function output(& $tcpdf) {
		return $tcpdf->Output('','S');
	}
	
	public function stripHTML($html) {
		$html = str_replace(array("\n", "\t", "\r"), '', $html);
		$html = preg_replace('/\<\/?[aA][^\>]*\>/', '', $html);
		return $html;
	}
	
	public static function admin_caption(){
		return 'Printing options';	
	}
	
	public static function get_logo_filename(){
		$i = self::Instance();
		return $i->get_data_dir().'company_logo.png';	
	}
}
?>
