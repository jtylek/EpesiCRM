<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-Utils
 * @subpackage calendar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CalendarCommon extends ModuleCommon {
	public static function print_event($ev,$mode='',$with_div=true) {
		$th = Base_ThemeCommon::init_smarty();
		$ex = self::process_event($ev);
		$th->assign('event_id',$ev['id']);
		$th->assign('draggable',!isset($ev['draggable']) || $ev['draggable']===true);
		$title = $ev['title'];
		$title_st = strip_tags($ev['title']);
		if(strlen($title_st)>15) $title_s = Utils_TooltipCommon::create(trim(substr($title_st,0,13),' ').'...',$title,false);
			else $title_s = $title;
		$th->assign('with_div',$with_div);
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
		if(isset($ev['custom_tooltip']))
			$th->assign('custom_tooltip',$ev['custom_tooltip']);
		ob_start();
		Base_ThemeCommon::display_smarty($th,'Utils_Calendar','event_tip');
		$tip = ob_get_clean();
		$th->assign('tip_tag_attrs',Utils_TooltipCommon::open_tag_attrs($tip,false));

		if(!isset($ev['view_action']) || $ev['view_action']===true)
			$th->assign('view_href', Module::create_href(array('UCev_id'=>$ev['id'], 'UCaction'=>'view')));
		elseif ($ev['view_action']!==false)
			$th->assign('view_href', $ev['view_action']);

		if(!isset($ev['edit_action']) || $ev['edit_action']===true)
			$th->assign('edit_href', Module::create_href(array('UCev_id'=>$ev['id'], 'UCaction'=>'edit')));
		elseif ($ev['edit_action']!==false)
			$th->assign('edit_href', $ev['edit_action']);

		$link_text = Module::create_href_js(array('UCev_id'=>$ev['id'], 'UCaction'=>'move','UCdate'=>'__YEAR__-__MONTH__-__DAY__'));
		if(!isset($ev['move_action']) || $ev['move_action']===true)
			$th->assign('move_href', Utils_PopupCalendarCommon::create_href('move_event'.str_replace(array('#','-'),'_',$ev['id']), $link_text,false,null,null,'popup.clonePosition(\'utils_calendar_event:'.$ev['id'].'\',{setWidth:false,setHeight:false,offsetTop:$(\'utils_calendar_event:'.$ev['id'].'\').getHeight()})'));

		if(!isset($ev['delete_action']) || $ev['delete_action']===true)
			$th->assign('delete_href', Module::create_confirm_href(Base_LangCommon::ts('Utils_Calendar','Delete this event?'),array('UCev_id'=>$ev['id'], 'UCaction'=>'delete')));
		elseif ($ev['delete_action']!==false)
			$th->assign('delete_href', $ev['delete_action']);

		$th->assign('handle_class','handle');
		$th->assign('custom_actions',$ev['actions']);
		Base_ThemeCommon::display_smarty($th,'Utils_Calendar','event'.($mode?'_'.$mode:''));
	}

	public static function process_event(& $row) {
		if(!isset($row['start']) && !(isset($row['timeless']) && $row['timeless']))
			trigger_error('Invalid return of event method: get(_all) (missing field \'start\' or \'timeless\' in '.print_r($row, true).')',E_USER_ERROR);
		if(!isset($row['duration']) || !is_numeric($row['duration']))
			trigger_error('Invalid return of event method: get(_all) (missing or not numeric field \'duration\' in '.print_r($row, true).')',E_USER_ERROR);
		if(!isset($row['title']))
			trigger_error('Invalid return of event method: get(_all) (missing field \'title\' in '.print_r($row, true).')',E_USER_ERROR);
		if(!isset($row['description']))
			trigger_error('Invalid return of event method: get(_all) (missing field \'description\' in '.print_r($row, true).')',E_USER_ERROR);
		if(!isset($row['id']))
			trigger_error('Invalid return of event method: get(_all) (missing field \'id\' in '.print_r($row, true).')',E_USER_ERROR);
		if(!isset($row['additional_info']))
			$row['additional_info'] = '';
		if(!isset($row['additional_info2']))
			$row['additional_info2'] = '';
		if(!isset($row['actions']))
			$row['actions'] = array();


		if(isset($row['timeless']) && $row['timeless']) {
			if(!isset($row['timeless_caption']))
				$row['timeless_caption'] = Base_LangCommon::ts('Utils_Calendar','timeless');
			$start_time = $row['timeless_caption'];
			$end_time = $start_time;
			$ev_start = strtotime($row['timeless']);
			$start_date = Base_RegionalSettingsCommon::time2reg($ev_start,false,true,false);
			$end_date = $start_date;
			$start_day = date('D',$ev_start);
			$end_day = $start_day;
			$start_t = $start_day.', '.$start_date;
			$end_t = $start_t;
		} else {
			if(!is_numeric($row['start']) && is_string($row['start'])) $row['start'] = strtotime($row['start']);
			if($row['start']===false)
				trigger_error('Invalid return of event method: get (start equal to null)',E_USER_ERROR);

			$row['end'] = $row['start']+$row['duration'];

			$ev_start = $row['start'];
			$ev_end = $row['end'];

			Base_RegionalSettingsCommon::set();
			$start_day = date('D',$ev_start);
			$end_day = date('D',$ev_end);
			Base_RegionalSettingsCommon::restore();

			$start_date = Base_RegionalSettingsCommon::time2reg($ev_start,false);
			$end_date = Base_RegionalSettingsCommon::time2reg($ev_end,false);
			$oneday = ($start_date==$end_date);
			if($oneday)
				$end_t = Base_RegionalSettingsCommon::time2reg($ev_end,2,false);

			$start_time = Base_RegionalSettingsCommon::time2reg($ev_start,2,false);
			$end_time = Base_RegionalSettingsCommon::time2reg($ev_end,2,false);
			if($start_date == Base_RegionalSettingsCommon::time2reg(time(),false))
				$start_t = Base_LangCommon::ts('Utils_Calendar','Today').', '.$start_time;
			elseif($start_date == Base_RegionalSettingsCommon::time2reg(time()+3600*24,false))
				$start_t = Base_LangCommon::ts('Utils_Calendar','Tomorrow').', '.$start_time;
			elseif($start_date == Base_RegionalSettingsCommon::time2reg(time()-3600*24,false))
				$start_t = Base_LangCommon::ts('Utils_Calendar','Yesterday').', '.$start_time;
			else
				$start_t = $start_day.', '.$start_date.' '.$start_time;
			if(!$oneday)
				$end_t = $end_day.', '.$end_date.' '.$end_time;
		}

		if(isset($row['fake_duration']))
			$duration_str = Base_RegionalSettingsCommon::seconds_to_words($row['fake_duration']);
		elseif($row['duration'])
			$duration_str = Base_RegionalSettingsCommon::seconds_to_words($row['duration']);
		else
			$duration_str = '';
		return array('duration'=>$duration_str,'start'=>$start_t,'end'=>$end_t,'start_time'=>$start_time,'end_time'=>$end_time,'start_date'=>$start_date,'end_date'=>$end_date,'start_day'=>$start_day,'end_day'=>$end_day);
	}

	public static function mobile_agenda($evmod,$extra_settings=array(),$time_shift=0,$view_func=null) {
		$settings = array(
			'custom_agenda_cols'=>null
		);
		$settings = array_merge($settings,$extra_settings);

		$start = time()+$time_shift;
		$end = $start + (7 * 24 * 60 * 60)+$time_shift;
		
		if(!IPHONE) {
			$columns = array(
				array('name'=>Base_LangCommon::ts('Utils_Calendar','Start'), 'order'=>'start', 'width'=>10),
				array('name'=>Base_LangCommon::ts('Utils_Calendar','Duration'), 'order'=>'end', 'width'=>5),
				array('name'=>Base_LangCommon::ts('Utils_Calendar','Title'), 'order'=>'title','width'=>10));
/*			$add_cols = array();
			if(is_array($settings['custom_agenda_cols'])) {
				$w = 50/count($settings['custom_agenda_cols']);
				foreach($settings['custom_agenda_cols'] as $k=>$col) {
					$columns[] = array('name'=>Base_LangCommon::ts('Utils_Calendar',$col), 'order'=>'cus_col_'.$k,'width'=>$w);
					$add_cols[] = $k;
				}
			}*/
		}
		
		//add data
		ob_start();
		$ret = call_user_func(array(str_replace('/','_',$evmod).'Common','get_all'),date('Y-m-d',$start),date('Y-m-d',$end));
		ob_get_clean();
		if(!is_array($ret))
			trigger_error('Invalid return of event method: get_all (not an array)',E_USER_ERROR);

		if(IPHONE) {
			print('<ul>');
			$date = null;
		} else {
			$data = array();
		}
		foreach($ret as $row) {
			$ex = Utils_CalendarCommon::process_event($row);
			if($view_func)
				$h = mobile_stack_href($view_func,array($row['id']),Base_LangCommon::ts('Utils_Calendar','View event'));
			else
				$h = '';
			if(IPHONE) {
				if($date!==$ex['start_date']) {
					$date=$ex['start_date'];
					print('</ul><h4>'.$date.'</h4><ul>');
				}
				$start = '<a '.$h.'>'.$ex['start'].'</a>';
				$duration = '<a '.$h.'>'.$ex['duration'].'</a>';
				$title = '<a '.$h.'>'.$row['title'].'</a>';
				print('<li class="arrow">'.$start.$duration.$title.'</li>');
			} else {
				$rrr = array(array('label'=>'<a '.$h.'>'.$ex['start'].'</a>','order_value'=>isset($row['timeless'])?strtotime($row['timeless']):$row['start']),'<a '.$h.'>'.$ex['duration'].'</a>','<a '.$h.'>'.$row['title'].'</a>');
//				foreach($add_cols as $a)
//					$rrr[] = $row['custom_agenda_col_'.$a];

				$data[] = $rrr;
			}
		}

		if(IPHONE) {
			print('</ul>');
		} else {
			Utils_GenericBrowserCommon::mobile_table($columns,$data,'start');
		}
	}
}


?>
