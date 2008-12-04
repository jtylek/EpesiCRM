<?php
/**
 * Simple reports for CRM Calendar
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage calendar-reports
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