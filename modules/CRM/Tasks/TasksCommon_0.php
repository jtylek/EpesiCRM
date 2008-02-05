<?php
/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-tasks
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_TasksCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Tasks";
	}

	public static function applet_info() {
		return "To do list";
	}

	public static function menu() {
		if(self::Instance()->acl_check('access'))
			return array('CRM'=>array('__submenu__'=>1,'Tasks'=>array()));
		else
			return array();
	}

	public static function body_access() {
		return self::Instance()->acl_check('access');
	}
	

}

?>