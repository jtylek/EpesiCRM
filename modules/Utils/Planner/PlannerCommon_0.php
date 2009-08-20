<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage planner
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_PlannerCommon extends ModuleCommon {
	public static function user_settings(){
		return array('Planners'=>array(
//			array('name'=>'per_page','label'=>'Records per page','type'=>'select','values'=>array(5=>5,10=>10,20=>20,50=>50,100=>100),'default'=>20),
//			array('name'=>'actions_position','label'=>'Position of \'Actions\' column','type'=>'radio','values'=>array(0=>'Left',1=>'Right'),'default'=>0),
//			array('name'=>'adv_search','label'=>'Advanced search by default','type'=>'bool','default'=>0),
//			array('name'=>'adv_history','label'=>'Advanced order history','type'=>'bool','default'=>0),
//			array('name'=>'display_no_records_message','label'=>'Hide \'No records found\' message','type'=>'bool','default'=>0),
//			array('name'=>'show_all_button','label'=>'Display \'Show all\' button','type'=>'bool','default'=>0)
			));
	}
	
	public static function get_resource_value($resource) {
		return $_SESSION['client']['utils_planner']['resources'][$resource]['value'];
	}
	
	public static function format_time($time) {
		static $base_unix_time = null;
		if ($base_unix_time===null) $base_unix_time = strtotime('1970-01-01 00:00');
		return Base_RegionalSettingsCommon::time2reg($base_unix_time+$time,'without_seconds',false,false);
	}
}

?>
