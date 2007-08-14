<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package utils
 * @subpackage file-download
 * @licence SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_FileDownloadInit_0 extends ModuleInit {

	public static function requires() {
		return array(
			array('name'=>'Base/Lang','version'=>0));
	}
	
	public static function provides() {
		return array();
	}

}

?>