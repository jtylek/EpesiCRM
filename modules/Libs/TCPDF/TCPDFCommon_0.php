<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-libs
 * @subpackage QuickForm
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_TCPDFCommon extends ModuleCommon {
	public static function user_settings(){
		return array('Printing settings'=>array(
			array('name'=>'page_format','label'=>'Page format','type'=>'select','values'=>array('A4'=>'A4','LETTER'=>'LETTER'),'default'=>'LETTER')
			));
	}
}
?>
