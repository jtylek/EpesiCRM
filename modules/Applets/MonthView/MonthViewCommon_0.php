<?php
/**
 * 
 * @author abisaga@telaxus.com
 * @copyright abisaga@telaxus.com
 * @license SPL
 * @version 0.1
 * @package applets-monthview
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_MonthViewCommon extends ModuleCommon {
	public static function applet_caption() {
		return "Month View";
	}

	public static function applet_info() {
		return "Displays Month and marks days with events";
	}

	public static function applet_settings() {
		$cols = CRM_Calendar_EventCommon::get_available_colors();
		$cols[0] = 'All';
		return array(	array('name'=>'days', 'label'=>'Look for events in', 'type'=>'select', 'default'=>'7', 'values'=>array('1'=>'1 day','2'=>'2 days','3'=>'3 days','5'=>'5 days','7'=>'1 week','14'=>'2 weeks', '30'=>'1 month', '61'=>'2 months')),
						array('name'=>'color', 'label'=>'Only events with selected color', 'type'=>'select', 'default'=>'0', 'values'=>$cols));
	}

}

?>