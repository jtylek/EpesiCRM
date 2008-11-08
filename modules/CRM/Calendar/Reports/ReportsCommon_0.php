<?php
/**
 * Simple reports for CRM Calendar
 * @author shacky@poczta.fm
 * @copyright shacky@poczta.fm
 * @license EPL
 * @version 0.1
 * @package crm-calendar--reports
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_ReportsCommon extends ModuleCommon {
	public static function body_access() {
		return self::Instance()->acl_check('access');
	}

	public static function menu() {
		if(self::Instance()->acl_check('access'))
			return array('CRM'=>array('__submenu__'=>1,'Calendar Reports'=>array()));
		else
			return array();
	}

}

?>