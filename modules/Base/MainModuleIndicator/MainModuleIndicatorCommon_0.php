<?php
/**
 * MainModuleIndicatorInstall class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage MainModuleIndicator
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_MainModuleIndicatorCommon extends Base_AdminModuleCommon {
	public static function admin_caption() {
		return "Module indicator settings";
	}	
}
?>