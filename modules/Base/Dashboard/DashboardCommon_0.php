<?php
/** 
 * Something like igoogle
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @version 0.1
 * @package epesi-base-extra
 * @subpackage dashboard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_DashboardCommon extends ModuleCommon {
	public static function menu() {
		if(Acl::is_user())
			return array('Dashboard'=>array());
		return array();
	}
	
	public static function admin_access() {
		return self::Instance()->acl_check('set default dashboard');
	}
	
	public static function admin_caption() {
		return 'Default dashboard';
	}
	
	public static function body_access() {
		return Acl::is_user();
	}
}
?>