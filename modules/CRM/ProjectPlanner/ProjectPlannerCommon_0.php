<?php
/**
 *
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package custom-projects-planner
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Custom_Projects_PlannerCommon extends ModuleCommon {

	public static function menu() {
		return array('Projects'=>array('__submenu__'=>1,'Planner'=>array(/*'__submenu__'=>1,'Employee view'=>array('view'=>'employee'),'Project view'=>array('view'=>'project'),'Overview'=>array('view'=>'overview')*/)));
	}

	public static function admin_caption() {
		return "Projects planner";
	}
}

?>
