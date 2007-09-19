<?php
/** 
 * Something like igoogle
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @version 0.1
 * @package epesi-base-extra
 * @subpackage activeboard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ActiveBoardCommon extends ModuleCommon {
	public static function menu() {
		if(Acl::is_user())
			return array('Dashboard'=>array());
		return array();
	}
	
	public static function body_access() {
		return Acl::is_user();
	}
}

?>