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

	////////////////////////////////////////
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
			$this->date = strtotime($this->get_unique_href_variable('date'));
		$link_text = $this->create_unique_href_js(array('date'=>'__YEAR__-__MONTH__-__DAY__'));
		$theme->assign('popup_calendar', Utils_PopupCalendarCommon::show('week_selector', $link_text));

		$header_month = '<a>'.date('M Y',$this->date).'</a>';
		$header_day = Utils_TooltipCommon::create('<a><table width=100%><tr><td width=50% align=left><span class=day_number >'.date('d',$this->date).'</span></td>'.
			'<td width=50% align=right>'.date('D',$this->date).'</td></tr></table></a>', "Click to add new event on ".date('d',$this->date)." ".date('m',$this->date).".");

		$theme->assign('header_month', $header_month);
		$theme->assign('header_day', $header_day);
		$theme->assign('timeline', $this->get_timeline());

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
