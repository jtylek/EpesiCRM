<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CalendarCommon extends ModuleCommon {
	public static function print_event($ev) {
		$th = Base_ThemeCommon::init_smarty();
		$ex = self::process_event($ev);
		$th->assign('event_id',$ev['id']);
		$th->assign('title',strip_tags($ev['title']));
		$th->assign('description',$ev['description']);
		$th->assign('color',$ev['color']);
		$th->assign('start',$ex['start']);
		$th->assign('start_short',$ex['start_short']);
		$th->assign('end',$ex['end']);
		$th->assign('duration',$ex['duration']);
		$th->assign('additional_info',$ev['additional_info']);
		ob_start();
		Base_ThemeCommon::display_smarty($th,'Utils_Calendar','event_tip');
		$tip = ob_get_clean();
		ob_start();
		Base_ThemeCommon::display_smarty($th,'Utils_Calendar','event_tip2');
		$tip2 = ob_get_clean();
		$th->assign('tip_tag_attrs',Utils_TooltipCommon::open_tag_attrs($tip));
		$th->assign('tip2_tag_attrs',Utils_TooltipCommon::open_tag_attrs($tip2));
		$th->assign('view_href', Module::create_href(array('UCev_id'=>$ev['id'], 'UCaction'=>'view')));
		$th->assign('edit_href', Module::create_href(array('UCev_id'=>$ev['id'], 'UCaction'=>'edit')));
		$link_text = Module::create_href_js(array('UCev_id'=>$ev['id'], 'UCaction'=>'move','UCdate'=>'__YEAR__-__MONTH__-__DAY__'));
		$th->assign('move_href', Utils_PopupCalendarCommon::create_href('move_event', $link_text,false,'day'));
		$th->assign('delete_href', Module::create_confirm_href(Base_LangCommon::ts('Utils_Calendar','Delete this event?'),array('UCev_id'=>$ev['id'], 'UCaction'=>'delete')));
		$th->assign('handle_class','handle');
		Base_ThemeCommon::display_smarty($th,'Utils_Calendar','event');
	}

	public static function process_event(& $row) {
		if(!isset($row['start']) || !isset($row['duration']) || !is_numeric($row['duration'])
		   || !isset($row['title']) || !isset($row['description'])
		   || !isset($row['timeless']) || !isset($row['id']))
			trigger_error('Invalid return of event method: get (missing field: '.print_r($row, true).')',E_USER_ERROR);

		if(!is_numeric($row['start']) && is_string($row['start'])) $row['start'] = strtotime($row['start']);
		if($row['start']===false)
			trigger_error('Invalid return of event method: get (start equal to null)',E_USER_ERROR);

		$row['end'] = $row['start']+$row['duration'];

		$ev_start = $row['start'];
		$ev_end = $row['end'];
		$oneday = (date('Y-m-d',$ev_end)==date('Y-m-d',$ev_start));

		Base_RegionalSettingsCommon::set_locale();
		$start_day = date('D',$ev_start);
		if(!$oneday)
			$end_day = date('D',$ev_end);
		else
			$end_t = Base_RegionalSettingsCommon::convert_24h($ev_end);
		Base_RegionalSettingsCommon::restore_locale();

		if($row['timeless']) {
			$start_short = Base_LangCommon::ts('Utils_Calendar','timeless');
			$start_t = $start_day.', '.Base_RegionalSettingsCommon::time2reg($ev_start,false);
			if($oneday)
				$end_t = $start_t;
			else
				$end_t = $end_day.', '.Base_RegionalSettingsCommon::time2reg($ev_end,false);
		} else {
			$start_short = Base_RegionalSettingsCommon::time2reg($ev_start,true,false);
			$start_t = $start_day.', '.Base_RegionalSettingsCommon::time2reg($ev_start);
			if(!$oneday)
				$end_t = $end_day.', '.Base_RegionalSettingsCommon::time2reg($ev_end);
		}

		$duration_str = self::duration2str($row['duration']);
		return array('duration'=>$duration_str,'start'=>$start_t,'end'=>$end_t,'start_short'=>$start_short);
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
