<?php
/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-projectplanner
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_ProjectPlannerCommon extends ModuleCommon {

	public static function menu() {
		return array('Projects'=>array('__submenu__'=>1,'Planner'=>array(/*'__submenu__'=>1,'Employee view'=>array('view'=>'employee'),'Project view'=>array('view'=>'project'),'Overview'=>array('view'=>'overview')*/)));
	}

}

?>