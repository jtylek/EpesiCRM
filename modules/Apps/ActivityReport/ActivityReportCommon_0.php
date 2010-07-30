<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage activityreport
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_ActivityReportCommon extends ModuleCommon {
	public static function menu(){
		return array('Reports'=>array_merge(array('__submenu__'=>1,'User Activity Report'=>array())));
	}

}

?>