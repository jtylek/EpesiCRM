<?php
/**
 * Import data from csv file
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage import
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_ImportCommon extends ModuleCommon {
	public static function menu() {
		if(self::Instance()->acl_check('import'))
			return array('CRM'=>array('__submenu__'=>1,'Import'=>array()));
		return array();
	}
	
	public static function body_access() {
		return self::Instance()->acl_check('import');
	}
}

?>