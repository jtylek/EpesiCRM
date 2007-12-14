<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_CalendarCommon extends ModuleCommon {
	public static function menu() {
		return array('CRM'=>array('__submenu__'=>1,'Calendar'=>array()));
	}

	public static function user_settings() {
		if(Acl::is_user()) {
			$start_day = array();
			foreach(range(0, 11) as $x)
				$start_day[$x.':00'] = Base_RegionalSettingsCommon::convert_24h($x.':00');
			$end_day = array();
			foreach(range(12, 24) as $x)
				$end_day[$x.':00'] = Base_RegionalSettingsCommon::convert_24h($x.':00');
			return array(
				'Calendar'=>array(
					array('name'=>'default_view','label'=>'Default view', 'type'=>'select', 'values'=>array('agenda'=>'Agenda', 'day'=>'Day', 'week'=>'Week', 'month'=>'Month', 'year'=>'Year'), 'default'=>'agenda'),

					array('name'=>'start_day','label'=>'Start day at', 'type'=>'select', 'values'=>$start_day, 'default'=>'8:00'),
					array('name'=>'end_day','label'=>'End day at', 'type'=>'select', 'values'=>$end_day, 'default'=>'17:00'),
					array('name'=>'interval','label'=>'Interval of grid', 'type'=>'select', 'values'=>array('0:15'=>'15 minutes','0:30'=>'30 minutes','1:00'=>'1 hour','2:00'=>'2 hours'), 'default'=>'1:00')
				)
			);
		}
		return array();
	}

	public static function applet_caption() {
		return "Agenda";
	}

	public static function applet_info() {
		return "Displays Clandar Agenda";
	}
}
?>
