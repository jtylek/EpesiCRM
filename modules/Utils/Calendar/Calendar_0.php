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
	private $event_module;

	public function construct($ev_mod, array $settings=null) {
		$this->lang = $this->init_module('Base/Lang');
		$this->settings = array_merge($this->settings,$settings);

		$this->event_module = str_replace('/','_',$ev_mod);
		if(ModuleManager::is_installed($this->event_module)==-1)
			trigger_error('Invalid event module: '.$this->event_module, E_USER_ERROR);
		$this->set_module_variable('event_module',$this->event_module);

		//default views
		if($this->settings['views']===null) $this->settings['views'] = & self::$views;

		//default date
		if($this->settings['default_date']===null) $this->settings['default_date']=time();
		$this->date = & $this->get_module_variable('date',$this->settings['default_date']);
	}

	public function set_date($d) {
		if(!is_numeric($d) && is_string($d)) $d = strtotime($d);
		$this->date = $d;
	}

	/**
	 * Returns timeline array with keys:
	 * - label - human readable start/end time
	 * - time - time shift in seconds between 0:00 and current time, not set for timeless
	 *
	 * @return array
	 */
	private function get_timeline() {
		static $timeline;
		if(isset($timeline)) return $timeline;

		$timeline = array();

		//timeless
		$timeline[] = array('label'=>$this->lang->t('Timeless'));

		//other
		$curr = strtotime($this->settings['start_day']);
		$last = strtotime($this->settings['end_day']);
		$interval = strtotime($this->settings['interval']);
		$zero_t = strtotime('0:00');
		if($last===false || $curr===false || $interval===false)
			trigger_error('Invalid start/end_day or interval.',E_USER_ERROR);
		$interval -= $zero_t;
		$timeline[] = array('label'=>Base_RegionalSettingsCommon::convert_24h($zero_t,false).' - '.Base_RegionalSettingsCommon::convert_24h($curr,false),'time'=>0);
		while($curr<$last) {
			$next = $curr+$interval;
			$timeline[] = array('label'=>Base_RegionalSettingsCommon::convert_24h($curr,false).' - '.Base_RegionalSettingsCommon::convert_24h($next,false),'time'=>($curr-$zero_t));
			$curr = $next;
		}
		$timeline[] = array('label'=>Base_RegionalSettingsCommon::convert_24h($curr,false).' - '.Base_RegionalSettingsCommon::convert_24h('23:59',false),'time'=>($curr-$zero_t));
		return $timeline;
	}

	public function body($arg = null) {
		if($this->isset_unique_href_variable('action')) {
			switch($this->get_unique_href_variable('action')) {
				case 'add':
					$this->push_event_action('add',array($this->get_unique_href_variable('time'),$this->get_unique_href_variable('timeless')=='1'));
					return;
				case 'switch':
					$views = array_flip($this->settings['views']);
					$view = $this->get_unique_href_variable('tab');
					if (isset($views[$view])) $switch_view = $views[$view];
						else break;
					$this->date = $this->get_unique_href_variable('time');
					break;
			}
		} elseif(isset($_REQUEST['UCaction']) && isset($_REQUEST['UCev_id']) && is_numeric($_REQUEST['UCev_id'])) {
			switch($_REQUEST['UCaction']) {
				case 'delete':
					$this->delete_event($_REQUEST['UCev_id']);
					break;
				case 'move':
					$this->move_event($_REQUEST['UCev_id'],$_REQUEST['UCdate']);
					break;
				case 'view':
				case 'edit':
					$this->push_event_action($_REQUEST['UCaction'],array($_REQUEST['UCev_id']));
					return;
			}
		}

		load_js($this->get_module_dir().'calendar.js');

		if(count($this->settings['views'])>1) {
			$tb = $this->init_module('Utils/TabbedBrowser');

			foreach($this->settings['views'] as $k=>$v) {
				if(!in_array($v,self::$views))
					trigger_error('Invalid view: '.$v,E_USER_ERROR);

				$tb->set_tab($this->lang->t($v),array($this, strtolower($v)));
				if(strcasecmp($v,$this->settings['default_view'])==0)
					$def_tab = $k;
			}
			if (isset($def_tab)) $tb->set_default_tab($def_tab);
			if (isset($switch_view))
				$tb->switch_tab($switch_view);

			$this->display_module($tb);
			$tb->tag();
		} else {
			$v = array_pop($this->settings['views']);
			if(!in_array($v,self::$views))
				trigger_error('Invalid view: '.$v,E_USER_ERROR);
			call_user_func(array($this,strtolower($v)));
		}

		Base_ActionBarCommon::add('add',$this->lang->t('Add event'),$this->create_unique_href(array('action'=>'add','time'=>$this->date)));
	}

	public function push_event_action($action,$arg=null) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main($this->event_module,$action,$arg);
	}

	public function delete_event($id) {
		call_user_func(array($this->event_module.'Common','delete'),$id);
	}

	public function move_event($ev_id,$time) {
		if(!is_numeric($time)) $time = strtotime($time);
		$ev = call_user_func(array($this->event_module.'Common','get'),$ev_id);
		if(!$ev['timeless'])
			$time += $ev['start']-strtotime(date('Y-m-d',$ev['start']));
		call_user_func(array($this->event_module.'Common','update'),$ev_id,$time,$ev['duration'],$ev['timeless']);
	}

	/**
	 * Get array of events between start and end time.
	 * Array has keys:
	 * - start - start time in seconds 1970
	 * - duration - duration in seconds
	 * - end - end time in seconds 1970
	 * - timeless - please treat start and end time as date
	 * - title
	 * - description
	 *
	 * @return array
	 */
	private function get_events($start,$end) {
		if(!is_numeric($start) && is_string($start)) $start = strtotime($start);
		if(!is_numeric($end) && is_string($end)) $end = strtotime($end);

		$ret = call_user_func(array($this->event_module.'Common','get_all'),$start,$end);
		if(!is_array($ret))
			trigger_error('Invalid return of event method: get (not an array)',E_USER_ERROR);
		return $ret;
	}

	private function print_event($ev) {
		print('<div id="utils_calendar_event:'.$ev['id'].'" class="utils_calendar_event">');
		Utils_CalendarCommon::print_event($ev);
		print('</div>');
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
			array('name'=>$this->lang->t('Start'), 'order'=>'start', 'width'=>15),
			array('name'=>$this->lang->t('Duration'), 'order'=>'end', 'width'=>15),
			array('name'=>$this->lang->t('Title'), 'order'=>'title','width'=>15),
			array('name'=>$this->lang->t('Description'), 'order'=>'description','width'=>30)
		);
		$gb->set_table_columns( $columns );

		//add data
		$ret = $this->get_events($start,$end);
		foreach($ret as $row) {
			$r = $gb->get_new_row();

			$ex = Utils_CalendarCommon::process_event($row);

			$r->add_data($ex['start'],Utils_TooltipCommon::create($ex['duration'],$ex['end']),$row['title'],$row['description']);

			$r->add_action($this->create_confirm_callback_href($this->lang->ht('Delete this event?'),array($this,'delete_event'),$row['id']),'Delete');
			$r->add_action($this->create_callback_href(array($this,'push_event_action'),array('edit',$row['id'])),'Edit');
			$r->add_action($this->create_callback_href(array($this,'push_event_action'),array('view',$row['id'])),'View');
		}

		$theme->assign('agenda',$this->get_html_of_module($gb,array(false),'automatic_display'));

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
		$theme->assign('popup_calendar', Utils_PopupCalendarCommon::show('day_selector', $link_text,false,'day',$this->settings['first_day_of_week']));

		$header_day = array('number'=>date('d',$this->date),
							'label'=>date('l',$this->date),
							'label_short'=>date('D',$this->date)
							);

		$theme->assign('header_month', date('F',$this->date));
		$theme->assign('link_month', $this->create_unique_href(array('action'=>'switch','time'=>$this->date, 'tab'=>'Month')));
		$theme->assign('header_year', date('Y',$this->date));
		$theme->assign('link_year', $this->create_unique_href(array('action'=>'switch','time'=>$this->date, 'tab'=>'Year')));
		$theme->assign('header_day', $header_day);

		$timeline = $this->get_timeline();
		$today_t = strtotime(date('Y-m-d',$this->date));
		$dnd = array();
		foreach($timeline as & $v) {
			$id = 'UCcell_'.(isset($v['time'])?($today_t+$v['time']):$today_t.'_timeless');
			$v['id'] = $id;
			if(isset($v['time']))
				$dnd[] = array($today_t+$v['time']);
			else
				$dnd[] = array($today_t,1);
		}
		$theme->assign('timeline', $timeline);

		$theme->assign('day_view_label', $this->lang->t('Day calendar'));
		$theme->assign('timeless_label', $this->lang->t('Timeless'));

		$theme->assign('trash_id','UCtrash');

		$theme->display('day');

		//data
		$ret = $this->get_events(date('Y-m-d',$this->date),date('Y-m-d',$this->date+86400));
		foreach($ret as $ev) {
			$ev_start = $ev['start']-$today_t;
			if($ev_start<0 || $ev_start>=86400) continue;

			$this->print_event($ev);

			if($ev['timeless'])
				$dest_id = 'UCcell_'.$today_t.'_timeless';
			else {
				$ct = count($timeline);
				for($i=1, $j=2; $j<$ct; $i++,$j++)
					if($timeline[$i]['time']<=$ev_start && $ev_start<$timeline[$j]['time'])
						break;
				$dest_id = $timeline[$i]['id'];
			}
			$this->js('Utils_Calendar.add_event(\''.Epesi::escapeJS($dest_id,false).'\',\''.$ev['id'].'\' ,\''.Epesi::escapeJS($ev['title'],false).'\')');
		}
		$this->js('Utils_Calendar.activate_dnd(\''.Epesi::escapeJS(json_encode($dnd),false).'\','.
				'\''.Epesi::escapeJS($this->create_unique_href_js(array('action'=>'add','time'=>'__TIME__','timeless'=>'__TIMELESS__')),false).'\','.
				'\''.Epesi::escapeJS($this->get_path(),false).'\','.
				'\''.CID.'\')');
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
			if($sh==-7) {
				$sh=0;
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
		$theme->assign('popup_calendar', Utils_PopupCalendarCommon::show('week_selector', $link_text,false,'day',$this->settings['first_day_of_week']));

		$week_shift = 86400*$this->get_module_variable('week_shift',0);

		$first_day_of_displayed_week = date('w', $this->date)-$this->settings['first_day_of_week'];
		if ($first_day_of_displayed_week<0) $first_day_of_displayed_week += 7;
		$first_day_of_displayed_week *= 86400;
		$dis_week_from = strtotime(date('Y-m-d',$this->date+$week_shift-$first_day_of_displayed_week));

		//headers
		$day_headers = array();
		if (date('m',$dis_week_from)!=date('m',$dis_week_from+518400)) {
			$second_span_width = date('d',$dis_week_from+518400);
			$header_month = array('first_span'=>array(
									'colspan'=>7-$second_span_width,
									'month'=>date('M',$dis_week_from),
									'month_link'=>$this->create_unique_href(array('action'=>'switch','time'=>$dis_week_from, 'tab'=>'Month')),
									'year'=>date('Y',$dis_week_from),
									'year_link'=>$this->create_unique_href(array('action'=>'switch','time'=>$dis_week_from, 'tab'=>'Year'))),
								'second_span'=>array(
									'colspan'=>$second_span_width,
									'month'=>date('M',$dis_week_from+518400),
									'month_link'=>$this->create_unique_href(array('action'=>'switch','time'=>$dis_week_from+518400, 'tab'=>'Month')),
									'year'=>date('Y',$dis_week_from+518400),
									'year_link'=>$this->create_unique_href(array('action'=>'switch','time'=>$dis_week_from+518400, 'tab'=>'Year'))
									));
		} else {
			$header_month = array('first_span'=>array(
									'colspan'=>7,
									'month'=>date('M',$dis_week_from),
									'month_link'=>$this->create_unique_href(array('action'=>'switch','time'=>$dis_week_from, 'tab'=>'Month')),
									'year'=>date('Y',$dis_week_from),
									'year_link'=>$this->create_unique_href(array('action'=>'switch','time'=>$dis_week_from, 'tab'=>'Year'))),
									);
		}
		for ($i=0; $i<7; $i++) {
			$that_day = $dis_week_from+$i*86400;
			$day_headers[] = array(
						'date'=>date('d D', $that_day),
						'style'=>(date('Y-m-d',$that_day)==date('Y-m-d')?'today':'other'),
						'link' => $this->create_unique_href(array('action'=>'switch','time'=>$that_day, 'tab'=>'Day'))
						);
		}

		$theme->assign('header_month', $header_month);
		$theme->assign('day_headers', $day_headers);

		//timeline and ids
		$timeline = $this->get_timeline();
		$time_ids = array();
		$dnd = array();
		for ($i=0; $i<7; $i++) {
			$time_ids[$i] = array();
			$today_t = strtotime(date('Y-m-d',$dis_week_from+$i*86400));
			foreach($timeline as & $v) {
				$id = 'UCcell_'.(isset($v['time'])?($today_t+$v['time']):$today_t.'_timeless');
				$time_ids[$i][] = $id;
				if(isset($v['time']))
					$dnd[] = array($today_t+$v['time']);
				else
					$dnd[] = array($today_t,1);
			}
		}
		$theme->assign('time_ids', $time_ids);
		$theme->assign('timeline', $timeline);

		$theme->assign('week_view_label', $this->lang->t('Week calendar'));
		$theme->assign('trash_id','UCtrash');
		//ok, display
		$theme->display('week');

		//data
		$ret = $this->get_events($dis_week_from,$dis_week_from+7*86400);
		foreach($ret as $k=>$ev) {
			$this->print_event($ev);
			$today_t = strtotime(date('Y-m-d',$ev['start']));
			if($ev['timeless']) {
				$dest_id = 'UCcell_'.$today_t.'_timeless';
			} else {
				$ev_start = $ev['start']-$today_t;
				$ct = count($timeline);
				for($i=1, $j=2; $j<$ct; $i++,$j++)
					if($timeline[$i]['time']<=$ev_start && $ev_start<$timeline[$j]['time'])
						break;
				$dest_id = 'UCcell_'.($today_t+$timeline[$i]['time']);
			}
			$this->js('Utils_Calendar.add_event(\''.Epesi::escapeJS($dest_id,false).'\', \''.$ev['id'].'\', \''.Epesi::escapeJS($ev['title'],false).'\')');
		}
		$this->js('Utils_Calendar.activate_dnd(\''.Epesi::escapeJS(json_encode($dnd),false).'\','.
				'\''.Epesi::escapeJS($this->create_unique_href_js(array('action'=>'add','time'=>'__TIME__','timeless'=>'__TIMELESS__')),false).'\','.
				'\''.Epesi::escapeJS($this->get_path(),false).'\','.
				'\''.CID.'\')');
	}

	//////////////////////////////////////////////////////
	// month and year
	public function month_array($date) {
		$first_day_of_month = strtotime(date('Y-m-', $date).'01');
		$diff = date('w', $first_day_of_month)-$this->settings['first_day_of_week'];
		if ($diff<0) $diff += 7;
		$currday = $first_day_of_month-86400*($diff);
		$curmonth = date('m', $date);

		$month = array();
		$today = date('Y-m-d');
		while (date('m', $currday) != ($curmonth)%12+1) {
			$week = array();
			$weekno = date('W',$currday);
			$link = $this->create_unique_href(array('action'=>'switch','time'=>$currday, 'tab'=>'Week'));
			for ($i=0; $i<7; $i++) {
				$week[] = array(
							'day'=>date('j', $currday),
							'day_link' => $this->create_unique_href(array('action'=>'switch', 'time'=>$currday, 'tab'=>'Day')),
							'style'=>(date('m', $currday)==$curmonth)?(date('Y-m-d',$currday)==$today?'today':'current'):'other',
							'time'=>$currday
							);
				$currday += 86400;
			}
			$month[] = array(
							'week_label'=>$weekno,
							'week_link' => $link,
							'days'=>$week);
		}
		return $month;
	}

	public function month() {
		$theme = & $this->pack_module('Base/Theme');

		$theme->assign('nextyear_href', $this->create_callback_href(array($this,'set_date'),strtotime((date('Y',$this->date)+1).date('-m-d',$this->date))));
		$theme->assign('nextyear_label',$this->lang->ht('Next year'));
		$theme->assign('nextmonth_href', $this->create_callback_href(array($this,'set_date'),$this->date+86400*date('t',$this->date)));
		$theme->assign('nextmonth_label',$this->lang->ht('Next month'));
		$theme->assign('today_href', $this->create_callback_href(array($this,'set_date'),time()));
		$theme->assign('today_label', $this->lang->ht('Today'));
		$theme->assign('prevmonth_href', $this->create_callback_href(array($this,'set_date'),$this->date-86400*date('t',$this->date-86400*(date('d', $this->date)+1))));
		$theme->assign('prevmonth_label', $this->lang->ht('Previous month'));
		$theme->assign('prevyear_href', $this->create_callback_href(array($this,'set_date'),strtotime((date('Y',$this->date)-1).date('-m-d',$this->date))));
		$theme->assign('prevyear_label', $this->lang->ht('Previous year'));
		$theme->assign('info', $this->lang->t('Double&nbsp;click&nbsp;on&nbsp;cell&nbsp;to&nbsp;add&nbsp;event'));

		if ($this->isset_unique_href_variable('date'))
			$this->set_date($this->get_unique_href_variable('date'));

		$link_text = $this->create_unique_href_js(array('date'=>'__YEAR__-__MONTH__-__DAY__'));
		$theme->assign('popup_calendar', Utils_PopupCalendarCommon::show('week_selector', $link_text,false,'month'));

		$month = $this->month_array($this->date);
		$dnd = array();
		foreach($month as & $week) {
			foreach($week['days'] as & $day) {
				$day['id'] = 'UCcell_'.$day['time'];
				$dnd[] = array($day['time'],2);
			}
		}

		$day_headers = array();
		for ($i=0; $i<7; $i++)
			$day_headers[] = date('D', strtotime('Sun')+86400*($i+$this->settings['first_day_of_week']));

		$theme->assign('month_view_label', $this->lang->t('Month calendar'));
		$theme->assign('timeless_label', $this->lang->t('Timeless'));

		$theme->assign('day_headers', $day_headers);
		$theme->assign('month', $month);
		$theme->assign('month_label', date('F', $this->date));
		$theme->assign('year_label', date('Y', $this->date));
		$theme->assign('year_link', $this->create_unique_href(array('time'=>$this->date, 'tab'=>'Year','action'=>'switch')));
		$theme->assign('trash_id','UCtrash');

		$theme->display('month');


		//data
		$start_t = $month[0]['days'][0]['time'];
		$end_t = $month[count($month)-1]['days'][6]['time'];
		$ret = $this->get_events($start_t,$end_t);
		foreach($ret as $k=>$ev) {
			$this->print_event($ev);
			$ev_start = strtotime(date('Y-m-d',$ev['start']));
			$dest_id = 'UCcell_'.$ev_start;
			$this->js('Utils_Calendar.add_event(\''.Epesi::escapeJS($dest_id,false).'\', \''.$ev['id'].'\', \''.Epesi::escapeJS($ev['title'],false).'\')');
		}
		$this->js('Utils_Calendar.activate_dnd(\''.Epesi::escapeJS(json_encode($dnd),false).'\','.
				'\''.Epesi::escapeJS($this->create_unique_href_js(array('action'=>'add','time'=>'__TIME__','timeless'=>'__TIMELESS__')),false).'\','.
				'\''.Epesi::escapeJS($this->get_path(),false).'\','.
				'\''.CID.'\')');
	}

	public function year() {
		$theme = & $this->pack_module('Base/Theme');

		$theme->assign('nextyear_href', $this->create_callback_href(array($this,'set_date'),strtotime((date('Y',$this->date)+1).date('-m-d',$this->date))));
		$theme->assign('nextyear_label',$this->lang->ht('Next year'));
		$theme->assign('today_href', $this->create_callback_href(array($this,'set_date'),time()));
		$theme->assign('today_label', $this->lang->ht('Today'));
		$theme->assign('prevyear_href', $this->create_callback_href(array($this,'set_date'),strtotime((date('Y',$this->date)-1).date('-m-d',$this->date))));
		$theme->assign('prevyear_label', $this->lang->ht('Previous year'));
		$theme->assign('info', $this->lang->t('Double&nbsp;click&nbsp;on&nbsp;cell&nbsp;to&nbsp;add&nbsp;event'));

		if ($this->isset_unique_href_variable('date'))
			$this->set_date($this->get_unique_href_variable('date'));

		$link_text = $this->create_unique_href_js(array('date'=>'__YEAR__-__MONTH__-__DAY__'));
		$theme->assign('popup_calendar', Utils_PopupCalendarCommon::show('week_selector', $link_text,false,'year'));


		$day_headers = array();
		for ($i=0; $i<7; $i++)
			$day_headers[] = date('D', strtotime('Sun')+86400*($i+$this->settings['first_day_of_week']));

		$theme->assign('month_view_label', $this->lang->t('Year calendar'));
		$theme->assign('timeless_label', $this->lang->t('Timeless'));

		$year = array();
		for ($i=1; $i<=12; $i++) {
			$date = strtotime(date('Y',$this->date).'-'.str_pad($i, 2, '0', STR_PAD_LEFT).'-'.date('d',$this->date));
			$month = $this->month_array($date);
			$year[] = array('month' => $month,
							'month_link' => $this->create_unique_href(array('action'=>'switch','time'=>$date, 'tab'=>'Month')),
							'month_label' => date('F', $date),
							'year_label' => date('Y', $date)
							);
		}
		$theme->assign('year', $year);
		$theme->assign('day_headers', $day_headers);

		$theme->display('year');
	}

	////////////////////////////////////////
	public function caption() {
		return 'Calendar';
	}
}
?>
