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

class Utils_CalendarBusyReport extends Module {
	private static $views = array('Day','Week');
	private $settings = array('first_day_of_week'=>0,
				  'start_day'=>'8:00',
                  'custom_rows'=>null,
                  'timeline'=>true,
                  'default_view'=>'Week',
                  'views'=>null,
                  'busy_labels'=>array(),
				  'end_day'=>'17:00',
				  'interval'=>'1:00',
				  'default_date'=>null,
				  'head_col_width'=>'90px');
	private $date; //current date
    private $event_module;
	private $tb;
	private $displayed_events = array();
	private $custom_new_event_href_js = null;

	public function construct($ev_mod, array $settings=null, $custom_new_event_href_js=null) {
		$this->custom_new_event_href_js = $custom_new_event_href_js;

        $this->settings = array_merge($this->settings,$settings);

        $this->event_module = str_replace('/','_',$ev_mod);
        if(ModuleManager::is_installed($this->event_module)==-1)
            trigger_error('Invalid event module: '.$this->event_module, E_USER_ERROR);
        $this->set_module_variable('event_module',$this->event_module);

        if(!is_array($this->settings['custom_rows']))
            $this->settings['custom_rows'] = array('timeless'=>__('Timeless'));

        //default views
        if($this->settings['views']===null) $this->settings['views'] = & self::$views;

		//default date
		if($this->settings['default_date']===null) $this->settings['default_date']=time();

		$this->date = & $this->get_module_variable('date',$this->settings['default_date']);
		$this->date = strtotime(date('Y-m-d',$this->date));

		if($this->isset_unique_href_variable('date'))
			$this->set_date($this->get_unique_href_variable('date'));
		if($this->isset_unique_href_variable('week_date'))
			$this->set_week_date($this->get_unique_href_variable('week_date'));
		if($this->isset_unique_href_variable('shift_week_day'))
			$this->shift_week_day($this->get_unique_href_variable('shift_week_day'));

        if(count($this->settings['views'])>1) {
            $this->tb = $this->init_module(Utils_TabbedBrowser::module_name());

		    foreach($this->settings['views'] as $k=>$v) {
				if(!in_array($v,self::$views))
					trigger_error('Invalid view: '.$v,E_USER_ERROR);

				switch ($v) {
					case 'Day': $label = __('Day'); break;
					case 'Week': $label = __('Week'); break;
				}
				$this->tb->set_tab($label,array($this, strtolower($v)));
				if(strcasecmp($v,$this->settings['default_view'])==0)
					$def_tab = $k;
			}
			if (isset($def_tab)) $this->tb->set_default_tab($def_tab);
		}

		if($this->isset_unique_href_variable('action')) {
			switch($this->get_unique_href_variable('action')) {
				case 'add':
					$this->push_event_action('add',array($this->get_unique_href_variable('time'),($this->get_unique_href_variable('timeless')=='0')?false:$this->get_unique_href_variable('timeless')));
					return;
				case 'switch':
					$views = array_flip($this->settings['views']);
					$view = $this->get_unique_href_variable('tab');
					if (isset($views[$view])) $this->tb->switch_tab($views[$view]);
						else break;
					$this->date = $this->get_unique_href_variable('time');
					break;
			}
		} elseif(isset($_REQUEST['UCaction']) && isset($_REQUEST['UCev_id'])) {
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

	}

	public function get_current_view(){
		return $this->settings['views'][$this->tb->get_tab()];
	}

	public function settings($key,$val) {
		$this->settings[$key] = $val;
	}

	public function get_date() {
		return $this->date;
	}

	public function get_view() {
		if(isset($this->tb))
			return $this->settings['views'][$this->tb->get_tab()];
		return null;
	}

	public function get_week_start_time() {
		$week_shift = 86400*$this->get_module_variable('week_shift',0);
		$first_day_of_displayed_week = date('w', $this->date)-$this->settings['first_day_of_week'];
		if ($first_day_of_displayed_week<0) $first_day_of_displayed_week += 7;
		$first_day_of_displayed_week *= 86400;
		return strtotime(date('Y-m-d',$this->date+$week_shift-$first_day_of_displayed_week));
	}

	public function get_week_end_time() {
		return $this->get_week_start_time() + 7*86400;
	}

	public function get_day_start_time() {
		return strtotime(date('Y-m-d',$this->date));
	}

	public function get_day_end_time() {
		return $this->get_day_start_time() + 86400;
	}

	public function get_start_time() {
		switch($this->get_view()) {
			case 'Day':
				return $this->get_day_start_time();
			case 'Week':
				return $this->get_week_start_time();
			default: return 0;
		}
	}

	public function get_end_time() {
		switch($this->get_view()) {
			case 'Day':
				return $this->get_day_end_time();
			case 'Week':
				return $this->get_week_end_time();
			default: return 0;
		}
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
	private function get_timeline($date) {
		$timeline = array();

        //timeless
        foreach($this->settings['custom_rows'] as $key=>$label)
            $timeline[] = array('label'=>$label,'time'=>$key);

        if($this->settings['timeline']) {
            $interval = strtotime($date.' '.$this->settings['interval']);
            $zero_t = strtotime($date.' 0:00');
            $interval -= $zero_t;
            $start = strtotime($date.' '.$this->settings['start_day']);
            $end = strtotime($date.' '.$this->settings['end_day']);
            if($end===false || $start===false || $interval===false)
                trigger_error('Invalid start/end_day or interval.',E_USER_ERROR);
            $interval_shift = '+'.str_replace(':',' hours ',$this->settings['interval']).' mins';
            $used = array();
            if($end<$start) {
                $curr = strtotime('0:00');
                $x = $curr;
                while($x<$end) {
                    $x = $x+$interval;
                    $time = (strtotime($date.' '.date('H:i:s',$curr))-$zero_t);
                    if(isset($used[$time]))
                        $timeline[$used[$time]]['time'] = false;
                    $used[$time] = count($timeline);
                    $timeline[] = array('label'=>Base_RegionalSettingsCommon::time2reg($curr,2,false,false).' - '.Base_RegionalSettingsCommon::time2reg($x,2,false,false),'time'=>$time,'join_rows'=>1);
                    $curr = $x;
                }
                $timeline[] = array('label'=>Base_RegionalSettingsCommon::time2reg($curr,2,false,false).' - '.Base_RegionalSettingsCommon::time2reg($start,2,false,false),'time'=>(strtotime($date.' '.date('H:i:s',$curr))-$zero_t),'join_rows'=>ceil(($start-$curr)/$interval));
                $day_end = strtotime('23:59')-$interval;
                $curr = $start;
                $x = $curr;
                while($x<$day_end) {
                    $x = strtotime($interval_shift, $x);
                    $time = (strtotime($date.' '.date('H:i:s',$curr))-$zero_t);
                    if(isset($used[$time]))
                        $timeline[$used[$time]]['time'] = false;
                    $used[$time] = count($timeline);
                    $timeline[] = array('label'=>Base_RegionalSettingsCommon::time2reg($curr,2,false,false).' - '.Base_RegionalSettingsCommon::time2reg($x,2,false,false),'time'=>$time,'join_rows'=>1);
                    $curr = $x;
                }
                $timeline[] = array('label'=>Base_RegionalSettingsCommon::time2reg($curr,2,false,false).' - '.Base_RegionalSettingsCommon::time2reg('23:59',2,false,false),'time'=>(strtotime($date.' '.date('H:i:s',$curr))-$zero_t));
            } else {
                if(date('H:i:s',strtotime('0:00'))!=date('H:i:s',$start))
                    $timeline[] = array('label'=>Base_RegionalSettingsCommon::time2reg('0:00',2,false,false),'time'=>0,'join_rows'=>ceil((strtotime(date('H:i:s',$start))-strtotime('0:00'))/$interval));
                $x = $start;
                while($x<$end) {
                    $x = strtotime($interval_shift, $x);
    //					$start_serv = $start;
    //					$zero_t_serv = $zero_t;
                    $start_serv = Base_RegionalSettingsCommon::reg2time($date.' '.date('H:i:s',$start));
                    $zero_t_serv = Base_RegionalSettingsCommon::reg2time($zero_t);
                    $time = ($start_serv-$zero_t_serv);
                    if(isset($used[$time]))
                        $timeline[$used[$time]]['time'] = false;
                    $used[$time] = count($timeline);
                    $timeline[] = array('label'=>Base_RegionalSettingsCommon::time2reg($start,2,false,false),'time'=>$time,'join_rows'=>1);
                    $start = $x;
                }
                if($start<strtotime($date.' '.'23:59'))
                    $timeline[] = array('label'=>Base_RegionalSettingsCommon::time2reg($start,2,false,false),'time'=>(strtotime($date.' '.date('H:i:s',$start))-$zero_t));
            }
        }
		return $timeline;
	}

	public function body($arg = null) {
		load_js($this->get_module_dir().'calendar-jq.js');

		if(isset($this->tb)) {
			$this->display_module($this->tb);
			$this->tb->tag();
		} else {
			$kk = array_keys($this->settings['views']);
			$v = $this->settings['views'][$kk[0]];
			if(!in_array($v,self::$views))
				trigger_error('Invalid view: '.$v.' - '.print_r(self::$views,true),E_USER_ERROR);
			call_user_func(array($this,strtolower($v)));
		}

		if ($this->custom_new_event_href_js!==null)
			$jshref = call_user_func($this->custom_new_event_href_js, $this->date+time()-strtotime(date('Y-m-d')), '0','');
		else
			$jshref = $this->create_unique_href_js(array('action'=>'add','time'=>$this->date+time()-strtotime(date('Y-m-d'))));
		if ($jshref!==false) {
			$href = ' href="javascript:void(0)" onClick="'.str_replace('"','\'',$jshref).'" '; // TODO: regular escape didn't work
			Base_ActionBarCommon::add('add',__('Add event'),$href);
			Utils_ShortcutCommon::add(array('Ctrl','N'), 'function(){'.$jshref.'}');
		}
	}

	public function sort_events($a, $b) {
		if(!isset($a['timeless']) || !$a['timeless'])
			$a_start = strtotime(Base_RegionalSettingsCommon::time2reg($a['start'],true,true,true,false));
		else
			$a_start = strtotime($a['timeless']);
		if(!isset($b['timeless']) || !$b['timeless'])
			$b_start = strtotime(Base_RegionalSettingsCommon::time2reg($b['start'],true,true,true,false));
		else
			$b_start = strtotime($b['timeless']);

		return $a_start>$b_start;
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
		$ret = call_user_func(array($this->event_module.'Common','get_all'),$start,$end);
		if(!is_array($ret))
			trigger_error('Invalid return of event method: get_all (not an array)',E_USER_ERROR);
		usort($ret,array($this,'sort_events'));
		return $ret;
	}

	////////////////////////////////////////////////////////////////////
	// day
	public function day() {
		$theme = $this->pack_module(Base_Theme::module_name());

		$theme->assign('next_href', $this->create_unique_href(array('date'=>date('Y-m-d',$this->date+24*3600))));
		$theme->assign('next_label',__('Next day'));
		$theme->assign('today_href', $this->create_unique_href(array('date'=>date('Y-m-d'))));
		$theme->assign('today_label', __('Today'));
		$theme->assign('prev_href', $this->create_unique_href(array('date'=>date('Y-m-d',$this->date-24*3600))));
		$theme->assign('prev_label', __('Previous day'));
		$link_text = $this->create_unique_href_js(array('date'=>'__YEAR__-__MONTH__-__DAY__'));
		$theme->assign('popup_calendar', Utils_PopupCalendarCommon::show('day_selector', $link_text,'day',$this->settings['first_day_of_week']));

		$header_day = array('number'=>date('d',$this->date),
							'label'=>__date('l',$this->date),
							'label_short'=>__date('D',$this->date)
							);

		$theme->assign('header_month', __date('F',$this->date));
		$theme->assign('link_month', $this->create_unique_href(array('action'=>'switch','time'=>$this->date, 'tab'=>'Month')));
		$theme->assign('header_year', __date('Y',$this->date));
		$theme->assign('link_year', $this->create_unique_href(array('action'=>'switch','time'=>$this->date, 'tab'=>'Year')));
		$theme->assign('header_day', $header_day);

		$timeline = $this->get_timeline(date('Y-m-d',$this->date));
		$today_t = Base_RegionalSettingsCommon::reg2time(date('Y-m-d',$this->date));
		$today_t_timeless = strtotime(date('Y-m-d',$this->date));
		$report = array();
		foreach($timeline as & $v) {
			if(is_string($v['time'])) {
				$ii = $today_t_timeless.'_'.$v['time'];
				$v['id'] = $ii;
				$report[$ii] = array();
			} elseif($v['time']!==false) {
				$ii = $today_t+$v['time'];
				$v['id'] = $ii;
				$report[$ii] = array();
			}
		}

		$theme->assign('timeline', $timeline);

		$theme->assign('day_view_label', __('Day calendar'));

		$theme->assign('weekend', date('N',$this->date)>=6);

		$theme->assign('head_col_width',$this->settings['head_col_width']);

		$navigation_bar_additions = '';
		if (is_callable(array($this->event_module, 'get_navigation_bar_additions'))) {
			$event_module_instance = $this->init_module($this->event_module);
			$navigation_bar_additions = call_user_func(array($event_module_instance,'get_navigation_bar_additions'), '', '');
		}
		$theme->assign('navigation_bar_additions', $navigation_bar_additions);

		//data
		//$this->date = strtotime(date('Y-m-d 00:00:00',$this->date));
		//$start = Base_RegionalSettingsCommon::reg2time($this->date);
		//$end = Base_RegionalSettingsCommon::reg2time($this->date+2*86400);
		$start = date('Y-m-d',$this->date);
		$end = date('Y-m-d',$this->date+3600*36);
		$ret = $this->get_events($start, $end);
		$this->displayed_events = array('start'=>$start, 'end'=>$end,'events'=>$ret);
		$custom_keys = $this->settings['custom_rows'];
		$busy_labels = $this->settings['busy_labels'];
		foreach($ret as $ev) {
			if(!isset($ev['busy_label'])) continue;
			if(!is_array($ev['busy_label'])) $ev['busy_label'] = array($ev['busy_label']);
			
			ob_start();
			Utils_CalendarBusyReportCommon::print_event($ev,'day');
			$ev_print = ob_get_clean();
			
			if(isset($ev['timeless']) && $ev['timeless']) {
				if($ev['timeless']!==$start) continue;
				if(!isset($ev['custom_row_key']))
					$ev['custom_row_key'] = 'timeless';
				$dur = 1;
				$diff = 0;
			} else {
				$ev_start = $ev['start']-$today_t;//Base_RegionalSettingsCommon::reg2time(date('Y-m-d H:i:s',$ev['start']))
				if($ev_start<0 || $ev_start>=86400) continue;
				$dur = 0;
				$day_start = explode(':',$this->settings['start_day']);
				$day_start = ($day_start[0]*60+$day_start[1])*60;
				$diff = ($day_start - ($ev['start'] - $today_t))/3600;
				$dur += ceil($ev['duration']/(strtotime($this->settings['interval'])-strtotime('0:00')));
			}

			if(isset($ev['custom_row_key'])) {
				if(isset($custom_keys[$ev['custom_row_key']])) {
					$dest_id = $today_t_timeless.'_'.$ev['custom_row_key'];
					if(isset($report[$dest_id])) {
						foreach($ev['busy_label'] as $busy_label) {
							if(!isset($report[$dest_id][$busy_label])) $report[$dest_id][$busy_label] = '';
							$report[$dest_id][$busy_label] .= $ev_print;
							if(!isset($busy_labels[$busy_label])) 
								$busy_labels[$busy_label]=$busy_label;
						}
					}
				} else {
					continue;
				}
			} elseif($this->settings['timeline']) {
				$ct = count($timeline);
				for($i=1, $j=2; $j<$ct; $i++,$j++) {
					while($timeline[$i]['time']===false) $i++;
					while(($timeline[$j]['time']===false || $i>=$j) && $j<$ct) $j++;
					if($j==$ct) break;
					if($timeline[$i]['time']<=$ev_start && $ev_start<$timeline[$j]['time'])
						break;
				}
				for($k=$i;$k<$i+$dur;$k++) {
					$dest_id = $timeline[$k]['id'];
					if(isset($report[$dest_id])) {
						foreach($ev['busy_label'] as $busy_label) {
							if(!isset($report[$dest_id][$busy_label])) $report[$dest_id][$busy_label] = '';
							$report[$dest_id][$busy_label] .= $ev_print;
							if(!isset($busy_labels[$busy_label])) 
								$busy_labels[$busy_label]=$busy_label;
						}
					}
				}
			}
		}
		
		$theme->assign('report',$report);
		$theme->assign('busy_labels',$busy_labels);
		
		$theme->display('day');

		if ($this->custom_new_event_href_js!==null)
			$jshref = call_user_func($this->custom_new_event_href_js, '__TIME__', '__TIMELESS__','__OBJECT__');
		else
			$jshref = $this->create_unique_href_js(array('action'=>'add','time'=>'__TIME__','timeless'=>'__TIMELESS__','object'=>'__OBJECT__'));
		eval_js('Utils_CalendarBusyReport.activate_dclick(\''.Epesi::escapeJS($jshref,false).'\')');
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
		$theme = $this->pack_module(Base_Theme::module_name());

		$theme->assign('next7_href', $this->create_unique_href(array('date'=>date('Y-m-d',$this->date+604800))));
		$theme->assign('next7_label',__('Next week'));
		$theme->assign('next_href', $this->create_unique_href(array('shift_week_day'=>1)));
		$theme->assign('next_label',__('Next day'));
		$theme->assign('today_href', $this->create_unique_href(array('date'=>date('Y-m-d'))));
		$theme->assign('today_label', __('Today'));
		$theme->assign('prev_href', $this->create_unique_href(array('shift_week_day'=>0)));
		$theme->assign('prev_label', __('Previous day'));
		$theme->assign('prev7_href', $this->create_unique_href(array('date'=>date('Y-m-d',$this->date-604800))));
		$theme->assign('prev7_label', __('Previous week'));
		$link_text = $this->create_unique_href_js(array('week_date'=>'__YEAR__-__MONTH__-__DAY__'));
		$theme->assign('popup_calendar', Utils_PopupCalendarCommon::show('week_selector', $link_text,'day',$this->settings['first_day_of_week']));

		$week_shift = $this->get_module_variable('week_shift',0);

		$first_day_of_displayed_week = date('w', $this->date)-$this->settings['first_day_of_week'];
		if ($first_day_of_displayed_week<0) $first_day_of_displayed_week += 7;
		$diff = $week_shift-$first_day_of_displayed_week;
		$dis_week_from = strtotime(($diff<0?$diff:'+'.$diff).' days',$this->date);

		//headers
		$day_headers = array();
		$today = Base_RegionalSettingsCommon::time2reg(null,false,true,true,false);
		if (date('m',$dis_week_from)!=date('m',$dis_week_from+518400)) {
			$second_span_width = date('d',$dis_week_from+518400);
			$header_month = array('first_span'=>array(
									'colspan'=>7-$second_span_width,
									'month'=>__date('M',$dis_week_from),
									'month_link'=>$this->create_unique_href(array('action'=>'switch','time'=>$dis_week_from, 'tab'=>'Month')),
									'year'=>date('Y',$dis_week_from),
									'year_link'=>$this->create_unique_href(array('action'=>'switch','time'=>$dis_week_from, 'tab'=>'Year'))),
								'second_span'=>array(
									'colspan'=>$second_span_width,
									'month'=>__date('M',$dis_week_from+518400),
									'month_link'=>$this->create_unique_href(array('action'=>'switch','time'=>$dis_week_from+518400, 'tab'=>'Month')),
									'year'=>date('Y',$dis_week_from+518400),
									'year_link'=>$this->create_unique_href(array('action'=>'switch','time'=>$dis_week_from+518400, 'tab'=>'Year'))
									));
		} else {
			$header_month = array('first_span'=>array(
									'colspan'=>7,
									'month'=>__date('M',$dis_week_from),
									'month_link'=>$this->create_unique_href(array('action'=>'switch','time'=>$dis_week_from, 'tab'=>'Month')),
									'year'=>date('Y',$dis_week_from),
									'year_link'=>$this->create_unique_href(array('action'=>'switch','time'=>$dis_week_from, 'tab'=>'Year'))),
									);
		}
		for ($i=0; $i<7; $i++) {
			$that_day = strtotime(date('Y-m-d',strtotime(date('Y-m-d 12:00:00',$dis_week_from))+3600*24*$i).' '.date('H:i:s',$dis_week_from));
			$day_headers[] = array(
						'date'=>date('d', $that_day).' '.__date('D', $that_day),
						'style'=>(date('Y-m-d',$that_day)==$today?'today':'other').(date('N',$that_day)>=6?'_weekend':''),
						'link' => $this->create_unique_href(array('action'=>'switch','time'=>$that_day, 'tab'=>'Day'))
						);
		}

		$theme->assign('header_month', $header_month);
		$theme->assign('day_headers', $day_headers);
		$theme->assign('head_col_width',$this->settings['head_col_width']);

		$report = array();

		//timeline and ids
		$time_ids = array();
		$timeline = array();
		for ($i=0; $i<7; $i++) {
			$time_ids[$i] = array();
			$today_t_timeless = strtotime(date('Y-m-d',strtotime(date('Y-m-d 12:00:00',$dis_week_from))+3600*24*$i).' '.date('H:i:s',$dis_week_from));
			$today_t = Base_RegionalSettingsCommon::reg2time(date('Y-m-d',$today_t_timeless));
			$today_date = date('Y-m-d',$today_t_timeless);
			$timeline[$today_date] = $this->get_timeline($today_date);
			$prev = null;
			foreach($timeline[$today_date] as & $v) {
				if($v['time']===false) {
					$time_ids[$i][] = false;
				} elseif(is_string($v['time'])) {
					$ii = $today_t_timeless.'_'.$v['time'];
					$time_ids[$i][] = $ii;
					$report[$ii] = array();
//					eval_js('jq("#UCcell_'.$ii.'").html("'.$ii.'");'); // *DEBUG*
				} else {
					$ii = $today_t+$v['time'];
					$time_ids[$i][] = $ii;
					$report[$ii] = array();
//					eval_js('jq("#UCcell_'.$ii.'").html("'.Base_RegionalSettingsCommon::time2reg($ii).'");'); // *DEBUG*
				}
				$prev = $v;
			}
		}
		$navigation_bar_additions = '';
		if (is_callable(array($this->event_module, 'get_navigation_bar_additions'))) {
			$event_module_instance = $this->init_module($this->event_module);
			$navigation_bar_additions = call_user_func(array($event_module_instance,'get_navigation_bar_additions'), '', '');
		}
		$theme->assign('navigation_bar_additions', $navigation_bar_additions);
		$theme->assign('time_ids', $time_ids);
		$theme->assign('timeline', reset($timeline));

		$theme->assign('week_view_label', __('Week calendar'));

		//data
		//$dis_week_from = Base_RegionalSettingsCommon::reg2time($dis_week_from);
		//$dis_week_to = $dis_week_from+7*86400-1;
		$dis_week_to = date('Y-m-d',$dis_week_from+7.5*86400);
		$dis_week_from = date('Y-m-d',$dis_week_from);
		$ret = $this->get_events($dis_week_from,$dis_week_to);
		$this->displayed_events = array('start'=>$dis_week_from, 'end'=>$dis_week_to,'events'=>$ret);
		$custom_keys = $this->settings['custom_rows'];
		$busy_labels = $this->settings['busy_labels'];
		foreach($ret as $k=>$ev) {
			if(!isset($ev['busy_label'])) continue;
			if(!is_array($ev['busy_label'])) $ev['busy_label'] = array($ev['busy_label']);
			
			ob_start();
			Utils_CalendarBusyReportCommon::print_event($ev);
			$ev_print = ob_get_clean();
			
			$day_start = explode(':',$this->settings['start_day']);
			$day_start = ($day_start[0]*60+$day_start[1])*60;
			if (!isset($ev['start'])) $diff = 1;
			else $diff = ($day_start - ($ev['start'] - $today_t))/3600;
			$dur = ceil($ev['duration']/(strtotime($this->settings['interval'])-strtotime('0:00')));
			
			if(isset($ev['timeless']) && $ev['timeless']) {
				if(!isset($ev['custom_row_key']))
					$ev['custom_row_key'] = 'timeless';
				$today_t_timeless = strtotime($ev['timeless']);
				if(isset($custom_keys[$ev['custom_row_key']])) {
					$dest_id = $today_t_timeless.'_'.$ev['custom_row_key'];
					if(isset($report[$dest_id])) {
						foreach($ev['busy_label'] as $busy_label) {
							if(!isset($busy_labels[$busy_label])) 
								$busy_labels[$busy_label]=$busy_label;
							if(!isset($report[$dest_id][$busy_label])) $report[$dest_id][$busy_label] = '';
							$report[$dest_id][$busy_label] .= $ev_print;
						}
					}
				} else {
//					trigger_error('Invalid custom_row_key:'.$ev['custom_row_key'],E_USER_ERROR);
					continue;
				}
			} else {
				$today_t = Base_RegionalSettingsCommon::time2reg($ev['start'],true,true,true,false);
				$today_date = date('Y-m-d',strtotime($today_t));
				$today_t = strtotime(date('Y-m-d H:i:s',Base_RegionalSettingsCommon::reg2time($today_date)));
				$ev_start = $ev['start']-$today_t;
				if(!isset($timeline[$today_date])) continue;
				$ct = count($timeline[$today_date]);
				for($i=1, $j=2; $j<$ct; $i++,$j++) {
					while($timeline[$today_date][$i]['time']===false) $i++;
					while(($timeline[$today_date][$j]['time']===false || $i>=$j) && $j<$ct) $j++;
					if($j==$ct) break;
					if($timeline[$today_date][$i]['time']<=$ev_start && $ev_start<$timeline[$today_date][$j]['time'])
						break;
				}
				for($k=$i;$k<$i+$dur;$k++) {
					$dest_id = ($today_t+$timeline[$today_date][$k]['time']);
					if(isset($report[$dest_id])) {
						foreach($ev['busy_label'] as $busy_label) {
							if(!isset($busy_labels[$busy_label])) 
								$busy_labels[$busy_label]=$busy_label;
							if(!isset($report[$dest_id][$busy_label])) $report[$dest_id][$busy_label] = '';
							$report[$dest_id][$busy_label] .= $ev_print;
						}
					}
				}
			}
		}
		$theme->assign('report',$report);
		$theme->assign('busy_labels',$busy_labels);

		//ok, display
		$theme->display('week');

		if ($this->custom_new_event_href_js!==null)
			$jshref = call_user_func($this->custom_new_event_href_js, '__TIME__', '__TIMELESS__','__OBJECT__');
		else
			$jshref = $this->create_unique_href_js(array('action'=>'add','time'=>'__TIME__','timeless'=>'__TIMELESS__','object'=>'__OBJECT__'));
		eval_js('Utils_CalendarBusyReport.activate_dclick(\''.Epesi::escapeJS($jshref,false).'\')');
	}

	public function push_event_action($action,$arg=null) {
		Base_BoxCommon::push_module($this->event_module,$action,$arg);
	}

	public function delete_event($id) {
		call_user_func(array($this->event_module.'Common','delete'),$id);
	}

	public function move_event($ev_id,$time) {
		if(!is_numeric($time)) $time = strtotime($time);
		$ev = call_user_func(array($this->event_module.'Common','get'),$ev_id);
		if(!isset($ev['timeless']))
			$time += $ev['start']-strtotime(date('Y-m-d',$ev['start']));
		elseif(!isset($ev['custom_row_key']))
			$ev['custom_row_key'] = 'timeless';
		call_user_func_array(array($this->event_module.'Common','update'),array(&$ev_id,$time,$ev['duration'],isset($ev['custom_row_key'])?$ev['custom_row_key']:null));
		location();
	}
	
	public function get_displayed_events() {
		return $this->displayed_events;
	}

	////////////////////////////////////////
	public function caption() {
		return __('Calendar Busy Report');
	}
}

?>