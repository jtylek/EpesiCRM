<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Calendar extends Module {
	private $lang;
	private static $views = array('Agenda','Day','Week','Month','Year');
	private $settings = array('first_day_of_week'=>0,
				  'default_view'=>'Agenda',
				  'views'=>null,
				  'start_day'=>'8:00',
				  'end_day'=>'17:00',
				  'interval'=>'1:00',
				  'default_date'=>null);
	private $date; //current date

	public function construct(array $settings) {
		$this->lang = $this->init_module('Base/Lang');
		$this->settings = array_merge($this->settings,$settings);

		if($this->settings['views']===null) $this->settings['views'] = & self::$views;

		//default date
		if($this->settings['default_date']===null) $this->settings['default_date']=time();
		$this->date = $this->get_module_variable('date',$this->settings['default_date']);
	}

	private function get_timeline() {
		static $timeline;
		if(isset($timeline)) return $timeline;

		$timeline = array();
		$curr = strtotime($this->settings['start_day']);
		$last = strtotime($this->settings['end_day']);
		$interval = strtotime($this->settings['interval']);
		if($last===false || $curr===false || $interval===false)
			trigger_error('Invalid start/end_day or interval.',E_USER_ERROR);
		$interval -= strtotime('0:00');
		$timeline[] = array('start'=>Base_RegionalSettingsCommon::convert_24h('0:00',false),'end'=>Base_RegionalSettingsCommon::convert_24h($curr,false));
		while($curr<$last) {
			$next = $curr+$interval;
			$timeline[] = array('start'=>Base_RegionalSettingsCommon::convert_24h($curr,false),'end'=>Base_RegionalSettingsCommon::convert_24h($next,false));
			$curr = $next;
		}
		$timeline[] = array('start'=>Base_RegionalSettingsCommon::convert_24h($curr,false),'end'=>Base_RegionalSettingsCommon::convert_24h('23:59',false));
		return $timeline;
	}

	public function body($arg = null) {
		$tb = $this->init_module('Utils/TabbedBrowser');

		foreach($this->settings['views'] as $k=>$v) {
			if(!in_array($v,self::$views))
				trigger_error('Invalid view: '.$v,E_USER_ERROR);

			$tb->set_tab($this->lang->t($v),array($this, strtolower($v)));
			if(strcasecmp($v,$this->settings['default_view'])==0)
				$def_tab = $k;
		}
		if(isset($def_tab)) $tb->set_default_tab($def_tab);

		$this->display_module($tb);
	}

	////////////////////////////////////////
	public function agenda() {

	}

	public function day() {
		$theme = & $this->pack_module('Base/Theme');

		$header_month = '<a>'.date('M Y',$this->date).'</a>';
		$header_day = Utils_TooltipCommon::create('<a><table width=100%><tr><td width=50% align=left><span class=day_number >'.date('d',$this->date).'</span></td>'.
			'<td width=50% align=right>'.date('D',$this->date).'</td></tr></table></a>', "Click to add new event on ".date('d',$this->date)." ".date('m',$this->date).".");

		$theme->assign('header_month', $header_month);
		$theme->assign('header_day', $header_day);
		$theme->assign('timeline', $this->get_timeline());

		$theme->assign('next_href', $this->create_unique_href(array()));
		$theme->assign('next_label',$this->lang->ht('Next day'));
		$theme->assign('today_href', $this->create_unique_href(array()));
		$theme->assign('today_label', $this->lang->ht('Today'));
		$theme->assign('prev_href', $this->create_unique_href(array()));
		$theme->assign('prev_label', $this->lang->ht('Previous day'));
		$theme->assign('info', $this->lang->t('Double&nbsp;click&nbsp;on&nbsp;cell&nbsp;to&nbsp;add&nbsp;event'));
		$link_text = $this->create_unique_href_js(array( 'date'=>array('year'=>'__YEAR__', 'month'=>'__MONTH__', 'day'=>'__DAY__') ));
		$theme->assign('popup_calendar', Utils_PopupCalendarCommon::show('week_selector', $link_text));


		$theme->display('day');
	}

	public function week() {

	}

	public function month() {

	}

	public function year() {

	}

	////////////////////////////////////////
	public function caption() {
		return 'Calendar';
	}
}
?>
