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
	    if(Base_AclCommon::check_permission('View Activity Report'))
    		return array(_M('Reports')=>array_merge(array('__submenu__'=>1,_M('User Activity Report')=>array())));
	}

}

?>