<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-data
 * @subpackage usa-states
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Data_CountriesCommon extends Base_AdminModuleCommon {
	public static function admin_caption() {
		return "Countries";
	}
	
	public static function get() {
		return Utils_CommonDataCommon::get_array('Countries');
	}
}

?>