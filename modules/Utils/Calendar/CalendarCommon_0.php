<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CalendarCommon extends ModuleCommon {
	public static function print_event($ev,$mode='') {
		$th = Base_ThemeCommon::init_smarty();
		$ex = self::process_event($ev);
		$th->assign('event_id',$ev['id']);
		$th->assign('draggable',!isset($ev['draggable']) || $ev['draggable']===true);
		$title = strip_tags($ev['title']);
		if(strlen($title)>15) $title_s = Utils_TooltipCommon::create(trim(substr($title,0,13),' ').'...',$title,false);
			else $title_s = $title;
		$th->assign('title',$title);
		$th->assign('title_s',$title_s);
		$th->assign('description',$ev['description']);
		$th->assign('color',$ev['color']);
		$th->assign('start',$ex['start']);
		$th->assign('start_time',$ex['start_time']);
		$th->assign('end_time',$ex['end_time']);
		$th->assign('start_date',$ex['start_date']);
		$th->assign('end_date',$ex['end_date']);
		$th->assign('start_day',$ex['start_day']);
		$th->assign('end_day',$ex['end_day']);
		$th->assign('end',$ex['end']);
		$th->assign('duration',$ex['duration']);
		$th->assign('additional_info',$ev['additional_info']);
		$th->assign('additional_info2',$ev['additional_info2']);
		ob_start();
		Base_ThemeCommon::display_smarty($th,'Utils_Calendar','event_tip');
		$tip = ob_get_clean();
		$th->assign('tip_tag_attrs',Utils_TooltipCommon::open_tag_attrs($tip,false));
		if(!isset($ev['view_action']) || $ev['view_action']==true)
			$th->assign('view_href', Module::create_href(array('UCev_id'=>$ev['id'], 'UCaction'=>'view')));
		if(!isset($ev['edit_action']) || $ev['edit_action']==true)
			$th->assign('edit_href', Module::create_href(array('UCev_id'=>$ev['id'], 'UCaction'=>'edit')));
		$link_text = Module::create_href_js(array('UCev_id'=>$ev['id'], 'UCaction'=>'move','UCdate'=>'__YEAR__-__MONTH__-__DAY__'));
		if(!isset($ev['move_action']) || $ev['move_action']==true)
			$th->assign('move_href', Utils_PopupCalendarCommon::create_href('move_event'.$ev['id'], $link_text,false,null,null,'popup.clonePosition(\'utils_calendar_event:'.$ev['id'].'\',{setWidth:false,setHeight:false,offsetTop:$(\'utils_calendar_event:'.$ev['id'].'\').getHeight()})'));
		if(!isset($ev['delete_action']) || $ev['delete_action']==true)
			$th->assign('delete_href', Module::create_confirm_href(Base_LangCommon::ts('Utils_Calendar','Delete this event?'),array('UCev_id'=>$ev['id'], 'UCaction'=>'delete')));
		$th->assign('handle_class','handle');
		$th->assign('custom_actions',$ev['actions']);
		Base_ThemeCommon::display_smarty($th,'Utils_Calendar','event'.($mode?'_'.$mode:''));
	}

	public static function process_event(& $row) {
		if(!isset($row['start']))
			trigger_error('Invalid return of event method: get(_all) (missing field \'start\' in '.print_r($row, true).')',E_USER_ERROR);
		if(!isset($row['duration']) || !is_numeric($row['duration']))
			trigger_error('Invalid return of event method: get(_all) (missing or not numeric field \'duration\' in '.print_r($row, true).')',E_USER_ERROR);
		if(!isset($row['title']))
			trigger_error('Invalid return of event method: get(_all) (missing field \'title\' in '.print_r($row, true).')',E_USER_ERROR);
		if(!isset($row['description']))
			trigger_error('Invalid return of event method: get(_all) (missing field \'description\' in '.print_r($row, true).')',E_USER_ERROR);
		if(!isset($row['timeless']))
			trigger_error('Invalid return of event method: get(_all) (missing field \'timeless\' in '.print_r($row, true).')',E_USER_ERROR);
		if(!isset($row['id']))
			trigger_error('Invalid return of event method: get(_all) (missing field \'id\' in '.print_r($row, true).')',E_USER_ERROR);
		if(!isset($row['additional_info']))
			$row['additional_info'] = '';
		if(!isset($row['additional_info2']))
			$row['additional_info2'] = '';
		if(!isset($row['actions']))
			$row['actions'] = array();

		if(!is_numeric($row['start']) && is_string($row['start'])) $row['start'] = strtotime($row['start']);
		if($row['start']===false)
			trigger_error('Invalid return of event method: get (start equal to null)',E_USER_ERROR);

		$row['end'] = $row['start']+$row['duration'];

		$ev_start = $row['start'];
		$ev_end = $row['end'];
		$oneday = (date('Y-m-d',$ev_end)==date('Y-m-d',$ev_start));

		Base_RegionalSettingsCommon::set();
		$start_day = date('D',$ev_start);
		$end_day = date('D',$ev_end);
		Base_RegionalSettingsCommon::restore();
		
		if($oneday)
			$end_t = Base_RegionalSettingsCommon::time2reg($ev_end,2,false);
		$start_date = Base_RegionalSettingsCommon::time2reg($ev_start,false);
		$end_date = Base_RegionalSettingsCommon::time2reg($ev_end,false);

		if($row['timeless']) {
			$start_time = Base_LangCommon::ts('Utils_Calendar','timeless');
			$end_time = $start_time;
			$start_t = $start_day.', '.$start_date;
			if($oneday)
				$end_t = $start_t;
			else
				$end_t = $end_day.', '.$end_date;
		} else {
			$start_time = Base_RegionalSettingsCommon::time2reg($ev_start,2,false);
			$end_time = Base_RegionalSettingsCommon::time2reg($ev_end,2,false);
			$start_t = $start_day.': '.$start_date.' '.$start_time;
			if(!$oneday)
				$end_t = $end_day.': '.$end_date.' '.$end_time;
		}

		$duration_str = self::duration2str($row['duration']);
		return array('duration'=>$duration_str,'start'=>$start_t,'end'=>$end_t,'start_time'=>$start_time,'end_time'=>$end_time,'start_date'=>$start_date,'end_date'=>$end_date,'start_day'=>$start_day,'end_day'=>$end_day);
	}

	private static function duration2str($duration) {
		$sec = $duration%60;
		$duration = floor($duration/60);
		if($duration>0) {
			$min = $duration%60;
			$duration = floor($duration/60);
			if($duration>0) {
				$hour = $duration%24;
				$duration = floor($duration/24);
				if($duration>0) {
					$days = $duration;
					$duration_str = Base_LangCommon::ts('Utils_Calendar','%d day(s) %d:%s',array($days, $hour,str_pad($min, 2, "0", STR_PAD_LEFT)));
				} else
					$duration_str = Base_LangCommon::ts('Utils_Calendar','%d:%s',array($hour,str_pad($min, 2, "0", STR_PAD_LEFT)));
			} else
				$duration_str = Base_LangCommon::ts('Utils_Calendar','00:%s',array(str_pad($min, 2, "0", STR_PAD_LEFT)));
		} else
			$duration_str = Base_LangCommon::ts('Utils_Calendar','%d sec',array($sec));
		return $duration_str;
	}

}


?>
