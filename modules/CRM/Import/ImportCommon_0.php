<?php
/**
 * Import data from csv file
 * @author shacky@poczta.fm
 * @copyright shacky@poczta.fm
 * @license SPL
 * @version 0.1
 * @package crm-import
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