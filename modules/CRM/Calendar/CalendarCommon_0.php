<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_CalendarCommon extends ModuleCommon {

	public static function body_access() {
		return self::Instance()->acl_check('access');
	}
	
	public static function menu() {
		if(self::Instance()->acl_check('access'))
			return array('CRM'=>array('__submenu__'=>1,'Calendar'=>array()));
		else
			return array();
	}

	public static function user_settings() {
		if(Acl::is_user()) {
/*			$start_day = array();
			foreach(range(0, 11) as $x)
				$start_day[$x.':00'] = Base_RegionalSettingsCommon::time2reg($x.':00',2,false,false);
			$end_day = array();
			foreach(range(12, 23) as $x)
				$end_day[$x.':00'] = Base_RegionalSettingsCommon::time2reg($x.':00',2,false,false);*/
			$start_day = array();
			foreach(range(0, 23) as $x)
				$start_day[$x.':00'] = Base_RegionalSettingsCommon::time2reg($x.':00',2,false,false);
			$end_day = $start_day;

			$color = array(1 => 'green', 2 => 'yellow', 3 => 'red', 4 => 'blue', 5=> 'gray', 6 => 'cyan', 7 =>'magenta');
			return array(
				'Calendar'=>array(
					array('name'=>'default_view','label'=>'Default view', 'type'=>'select', 'values'=>array('agenda'=>'Agenda', 'day'=>'Day', 'week'=>'Week', 'month'=>'Month', 'year'=>'Year'), 'default'=>'week'),

					array('name'=>'start_day','label'=>'Start day at', 'type'=>'select', 'values'=>$start_day, 'default'=>'8:00'),
					array('name'=>'end_day','label'=>'End day at', 'type'=>'select', 'values'=>$end_day, 'default'=>'17:00'),
					array('name'=>'interval','label'=>'Interval of grid', 'type'=>'select', 'values'=>array('0:30'=>'30 minutes','1:00'=>'1 hour','2:00'=>'2 hours'), 'default'=>'1:00'),
					array('name'=>'default_color','label'=>'Default event color', 'type'=>'select', 'values'=>$color, 'default'=>'1')
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

	public static function applet_settings() {
		return array(array('name'=>'days', 'label'=>'Look for events in', 'type'=>'select', 'default'=>'7', 'values'=>array('1'=>'1 day','2'=>'2 days','3'=>'3 days','5'=>'5 days','7'=>'1 week','14'=>'2 weeks')));
	}

	public static function search($word){
		if(!self::Instance()->acl_check('access'))
			return array();
		
		$attachs = Utils_AttachmentCommon::search_group('CRM/Calendar/Event',$word);
		$attach_ev_ids = array();
		foreach($attachs as $x) {
			if(ereg('CRM/Calendar/Event/([0-9]+)',$x['group'],$reqs))
				$attach_ev_ids[$reqs[1]] = true;
		}
		$attach_ev_ids2 = array_keys($attach_ev_ids);
		
		
		$query = 'SELECT ev.start,ev.title,ev.id FROM crm_calendar_event ev '.
					'WHERE (ev.title LIKE '.DB::Concat('\'%\'',DB::qstr($word),'\'%\'').
 					' OR ev.description LIKE '.DB::Concat('\'%\'',DB::qstr($word),'\'%\'').
 					(empty($attach_ev_ids)?'':' OR ev.id IN ('.implode(',',$attach_ev_ids2).')').
					')';
 		$recordSet = DB::Execute($query);
 		$result = array();

 		while (!$recordSet->EOF){
 			$row = $recordSet->FetchRow();
			if(isset($attach_ev_ids[$row['id']]))
 				$result['Event (attachment)#'.$row['id'].', '.$row['title']] = array('search_date'=>$row['start']);
			else
	 			$result['Event #'.$row['id'].', '.$row['title']] = array('search_date'=>$row['start']);
 		}
		
 		
		return $result;
	}



}
?>
