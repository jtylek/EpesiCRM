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
		$this->date = & $this->get_module_variable('date',$this->settings['default_date']);
	}

	public function set_date($d) {
		if(!is_numeric($d) && is_string($d)) $d = strtotime($d);
		$this->date = $d;
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
		$tb->tag();
	}

	//////////////////////////////////////////////
	// agenda
	public function agenda() {
		$theme = $this->pack_module('Base/Theme');

		/////////////// controls ////////////////////////
		$start = & $this->get_module_variable('agenda_start',$this->date);
		$end = & $this->get_module_variable('agenda_end',$this->date + (7 * 24 * 60 * 60));

		$form = $this->init_module('Libs/QuickForm',null,'agenda_frm');

		$form->addElement('datepicker', 'start', $this->lang->t('From'));
		$form->addElement('datepicker', 'end', $this->lang->t('To'));
		$form->addElement('submit', 'submit_button', $this->lang->ht('Show'));
		$form->addRule('select_start', 'Field required', 'required');
		$form->addRule('select_end', 'Field required', 'required');
		$form->setDefaults(array('start'=>$start,'end'=>$end));

		if($form->validate()) {
			$data = $form->exportValues();
			$start = strtotime($data['start']);
			$end = strtotime($data['end']);
		}

		$form->assign_theme('form', $theme, new HTML_QuickForm_Renderer_TCMSArraySmarty());

		//////////////// data ////////////////////////
		$gb = $this->init_module('Utils/GenericBrowser', null, 'agenda');
		$columns = array(
			array('name'=>$this->lang->t('Day'), 'order'=>'datetime_start', 'width'=>5),
			array('name'=>$this->lang->t('Date'), 'order'=>'datetime_start', 'width'=>6),
			array('name'=>$this->lang->t('Start'), 'order'=>'datetime_start', 'width'=>5),
			array('name'=>$this->lang->t('End'), 'order'=>'datetime_end', 'width'=>5),
			array('name'=>$this->lang->t('Title'), 'order'=>'details','width'=>15),
			array('name'=>$this->lang->t('Description'), 'order'=>'details','width'=>30)
		);
		$gb->set_table_columns( $columns );

		//add data here
		$gb->get_new_row()->add_data('x','y','z','1','2','3');
		//use $start and $end as timestamps

		$theme->assign('agenda',$this->get_html_of_module($gb));

		//////////////// display ///////////////
		$theme->display('agenda');
	}

	////////////////////////////////////////////////////////////////////
	// day
	public function day() {
		$theme = & $this->pack_module('Base/Theme');

		$theme->assign('next_href', $this->create_callback_href(array($this,'set_date'),$this->date+86400));
		$theme->assign('next_label',$this->lang->ht('Next day'));
		$theme->assign('today_href', $this->create_callback_href(array($this,'set_date'),time()));
		$theme->assign('today_label', $this->lang->ht('Today'));
		$theme->assign('prev_href', $this->create_callback_href(array($this,'set_date'),$this->date-86400));
		$theme->assign('prev_label', $this->lang->ht('Previous day'));
		$theme->assign('info', $this->lang->t('Double&nbsp;click&nbsp;on&nbsp;cell&nbsp;to&nbsp;add&nbsp;event'));
		if($this->isset_unique_href_variable('date'))
			$this->set_date($this->get_unique_href_variable('date'));
		$link_text = $this->create_unique_href_js(array('date'=>'__YEAR__-__MONTH__-__DAY__'));
		$theme->assign('popup_calendar', Utils_PopupCalendarCommon::show('day_selector', $link_text));

		$header_month = '<a>'.date('M Y',$this->date).'</a>';
		$header_day = Utils_TooltipCommon::create('<a><table width=100%><tr><td width=50% align=left><span class=day_number >'.date('d',$this->date).'</span></td>'.
			'<td width=50% align=right>'.date('D',$this->date).'</td></tr></table></a>', "Click to add new event on ".date('d',$this->date)." ".date('m',$this->date).".");

		$theme->assign('header_month', $header_month);
		$theme->assign('header_day', $header_day);
		$theme->assign('timeline', $this->get_timeline());

		$theme->assign('day_view_label', $this->lang->t('Day calendar'));
		$theme->assign('timeless_label', $this->lang->t('Timeless'));

		$theme->display('day');
	}

	///////////////////////////////////////////////////////
	// week
	public function shift_week_day($s) { //true=+1,false=-1
		$sh = & $this->get_module_variable('week_shift',0);
		if($s) {
			$sh++;
			if($sh==7) {
				$sh=0;
				$this->date+=604800; //next week
			}
		} else {
			$sh--;
			if($sh==-1) {
				$sh=6;
				$this->date-=604800; //prev week
			}
		}
	}
	public function set_week_date($d) {
		$this->set_date($d);
		$this->set_module_variable('week_shift',0);
	}

	public function week() {
		$theme = & $this->pack_module('Base/Theme');

		$theme->assign('next7_href', $this->create_callback_href(array($this,'set_date'),$this->date+604800));
		$theme->assign('next7_label',$this->lang->ht('Next week'));
		$theme->assign('next_href', $this->create_callback_href(array($this,'shift_week_day'),true));
		$theme->assign('next_label',$this->lang->ht('Next day'));
		$theme->assign('today_href', $this->create_callback_href(array($this,'set_week_date'),time()));
		$theme->assign('today_label', $this->lang->ht('Today'));
		$theme->assign('prev_href', $this->create_callback_href(array($this,'shift_week_day'),false));
		$theme->assign('prev_label', $this->lang->ht('Previous day'));
		$theme->assign('prev7_href', $this->create_callback_href(array($this,'set_date'),$this->date-604800));
		$theme->assign('prev7_label', $this->lang->ht('Previous week'));
		$theme->assign('info', $this->lang->t('Double&nbsp;click&nbsp;on&nbsp;cell&nbsp;to&nbsp;add&nbsp;event'));
		if($this->isset_unique_href_variable('date'))
			$this->set_week_date($this->get_unique_href_variable('date'));
		$link_text = $this->create_unique_href_js(array('date'=>'__YEAR__-__MONTH__-__DAY__'));
		$theme->assign('popup_calendar', Utils_PopupCalendarCommon::show('week_selector', $link_text));

		$theme->assign('timeline', $this->get_timeline());

		$week_shift = 86400*$this->get_module_variable('week_shift',0);


		$default_first_day_of_the_week = 1; // TODO 0..6, 0-sun, trzeba przeniesc do user_settings
		
		$first_day_of_displayed_week = date('w', $this->date)-$default_first_day_of_the_week;
		if ($first_day_of_displayed_week<0) $first_day_of_displayed_week += 7;
		$first_day_of_displayed_week *= 86400;
		$dis_week_from = $this->date+$week_shift-$first_day_of_displayed_week;

		$day_headers = array();
		if (date('m',$dis_week_from)!=date('m',$dis_week_from+518400)) {
			$second_span_width = date('d',$dis_week_from+518400);
			$header_month = array('first_span'=>array(
									'colspan'=>7-$second_span_width, 
									'label'=>date('M Y',$dis_week_from)), 
								'second_span'=>array(
									'colspan'=>$second_span_width, 
									'label'=>date('M Y',$dis_week_from+518400) 
									)); 
		} else {
			$header_month = array('first_span'=>array(
									'colspan'=>7, 
									'label'=>date('M Y',$dis_week_from)
									)); 
		}
		for ($i=0; $i<7; $i++)
			$day_headers[] = date('d D', $dis_week_from+$i*86400);
		$theme->assign('header_month', $header_month);
		$theme->assign('day_headers', $day_headers);

		$theme->assign('week_view_label', $this->lang->t('Week calendar'));
		$theme->assign('timeless_label', $this->lang->t('Timeless'));

		$theme->display('week');
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
