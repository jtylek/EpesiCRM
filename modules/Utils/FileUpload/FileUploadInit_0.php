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
 * Initialization
 * @package epesi-utils
 * @subpackage file-uploader
 */
class Utils_FileUploadInit_0 extends ModuleInit {

	public static function requires() {
		return array(
			array('name'=>'Libs/QuickForm','version'=>0),
			array('name'=>'Base/Lang','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>