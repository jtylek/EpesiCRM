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
 * @license SPL
 * @package epesi-libs
 * @subpackage tcpdf
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_TCPDFCommon extends ModuleCommon {
	public static function user_settings(){
		return array('Printing settings'=>array(
			array('name'=>'page_format','label'=>'Page format','type'=>'select','values'=>array('A4'=>'A4','LETTER'=>'LETTER','LEGAL'=>'LEGAL'),'default'=>'LETTER')
			));
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
