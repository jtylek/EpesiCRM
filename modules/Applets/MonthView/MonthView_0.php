<?php
/**
 * @author abisaga@telaxus.com
 * @copyright 2008 Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-applets
 * @subpackage monthview
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Applets_MonthView extends Module {
	private $date;

	public function body() {
	
	}

	public function month_array($date, $mark = array()) {
		$first_day_of_month = strtotime(date('Y-m-', $date).'01');
		$diff = date('w', $first_day_of_month)-Utils_PopupCalendarCommon::get_first_day_of_week();
		if ($diff<0) $diff += 7;
		$currday = strtotime(date('Y-m-d',$first_day_of_month-86400*($diff)));
		$curmonth = date('m', $date);

		$month = array();
		$today = date('Y-m-d',strtotime(Base_RegionalSettingsCommon::time2reg(null,false,true,true,false)));
		$colors = CRM_Calendar_EventCommon::get_available_colors();
		while (date('m', $currday) != ($curmonth)%12+1) {
			$week = array();
			$weekno = date('W',$currday);
			$link = Base_BoxCommon::create_href($this, 'CRM_Calendar', null, null, null, array('jump_to_date'=>$currday, 'switch_to_tab'=>'Week'));
			for ($i=0; $i<7; $i++) {
				$main_month = date('m', $currday)==$curmonth;
				$next = array(
							'day'=>date('j', $currday),
							'day_link' => Base_BoxCommon::create_href($this, 'CRM_Calendar', null, null, null, array('jump_to_date'=>$currday, 'switch_to_tab'=>'Day')),
							'style'=>($main_month?(date('Y-m-d',$currday)==$today?'today':'current'):'other').(date('N',$currday)>=6?'_weekend':''),
							'time'=>$currday
							);
				if ($main_month && isset($mark[date('Y-m-d',$currday)])) {
					$next['style'].= ' event-'.$colors[$mark[date('Y-m-d',$currday)]];
				}
				$week[] = $next;
				$currday = strtotime(date('Y-m-d',strtotime(date('Y-m-d 12:00:00',$currday))+3600*24).' '.date('H:i:s',$currday));
			}
			$month[] = array(
							'week_label'=>$weekno,
							'week_link' => $link,
							'days'=>$week);
		}
		return $month;
	}

	public function applet($conf, & $opts) {
		$opts['go'] = false;
		$this->date = $this->get_module_variable_or_unique_href_variable('date');
		if ($this->date==null) $this->date = date('Y-m-15');
		$this->set_module_variable('date', $this->date);
		$this->date = strtotime($this->date);
		$theme = $this->pack_module('Base/Theme');

		$theme->assign('nextyear_href', $this->create_unique_href(array('date'=>date('Y-m-15',$this->date+30*24*60*60))));
		$theme->assign('today_href', $this->create_unique_href(array('date'=>date('Y-m-d'))));
		$theme->assign('prevyear_href', $this->create_unique_href(array('date'=>date('Y-m-15',$this->date-30*24*60*60))));

		$link_text = $this->create_unique_href_js(array('date'=>'__YEAR__-__MONTH__-__DAY__'));
		$theme->assign('popup_calendar', Utils_PopupCalendarCommon::show('week_selector', $link_text,'month',null,null,''));

		$day_headers = array();
		$day = strtotime('Sun');
		$day = strtotime('+'.Utils_PopupCalendarCommon::get_first_day_of_week().' days', $day);
		for ($i=0; $i<7; $i++) {
			$day_headers[] = __date('D', $day);
			$day = strtotime('+1 day', $day);
		}

		$year = array();
		
		$me = CRM_ContactsCommon::get_my_record(); 
		CRM_Calendar_EventCommon::$filter = '('.$me['id'].')';
		$ret = call_user_func(array('CRM_Calendar_EventCommon','get_event_days'),date('Y-m-01',$this->date),date('Y-m-d',strtotime(date('Y-m-t', $this->date))+86400));
		
		$month = $this->month_array($this->date, $ret);
		$year[] = array('month' => $month,
						'month_link' => Base_BoxCommon::create_href($this, 'CRM_Calendar', null, null, null, array('jump_to_date'=>$this->date, 'switch_to_tab'=>'Month')),
						'month_label' => __date('F', $this->date),
						'year_label' => date('Y', $this->date)
						);
		$theme->assign('year', $year);
		$theme->assign('day_headers', $day_headers);

		$theme->display('year');
	}
	
}

?>