<?php
/**
 * Displays busy report of employees
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license Commercial
 * @version 0.1
 * @package epesi-Utils
 * @subpackage CalendarBusyReport
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CalendarBusyReportCommon extends Base_AdminModuleCommon {
	public static function print_event($ev,$mode='',$with_div=true) {
		$th = Base_ThemeCommon::init_smarty();
		$ex = Utils_CalendarCommon::process_event($ev);
		$th->assign('event_id',$ev['id']);
		$th->assign('draggable',false);
		$title = $ev['title'];
		$title_st = strip_tags($ev['title']);
		$title_s = $title;
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
		$th->assign('show_hide_info',__('Click to show / hide menu'));
		$th->assign('additional_info',$ev['additional_info']);
		$th->assign('additional_info2',$ev['additional_info2']);
		if(isset($ev['custom_tooltip']))
			$th->assign('custom_tooltip',$ev['custom_tooltip']);
		ob_start();
		Base_ThemeCommon::display_smarty($th,'Utils_CalendarBusyReport','event_tip');
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
			$th->assign('move_href', Utils_PopupCalendarCommon::create_href('move_event'.str_replace(array('#','-'),'_',$ev['id']), $link_text,null,null,'jq(popup).clonePosition(\'#utils_calendar_event:'.$ev['id'].'\',{cloneWidth:false,cloneHeight:false,offsetTop:jq(\'#utils_calendar_event:'.$ev['id'].'\').height()})'));

		if(!isset($ev['delete_action']) || $ev['delete_action']===true)
			$th->assign('delete_href', Module::create_confirm_href(__('Delete this event?'),array('UCev_id'=>$ev['id'], 'UCaction'=>'delete')));
		elseif ($ev['delete_action']!==false)
			$th->assign('delete_href', $ev['delete_action']);

		$th->assign('handle_class','handle');
		$th->assign('custom_actions',$ev['actions']);
		Base_ThemeCommon::display_smarty($th,'Utils_CalendarBusyReport','event'.($mode?'_'.$mode:''));
	}

}

?>