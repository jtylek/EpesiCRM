<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage calendar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar extends Module {
	private $lp;
	
	public function new_event($type, $timestamp, $timeless) {
		if ($type!==null) {
			list($label,$id,$int_id) = explode('__',$type);
			$callback = DB::GetOne('SELECT handler_callback FROM crm_calendar_custom_events_handlers WHERE id=%d',$id);
		} else {
			$callback = DB::GetOne('SELECT handler_callback FROM crm_calendar_custom_events_handlers');
		}
		$callback = explode('::', $callback);
		$ret = call_user_func($callback, 'new_event', $timestamp, $timeless, $int_id, $this);
		if (!$ret) {
			$x = ModuleManager::get_instance('/Base_Box|0');
			if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
			return $x->pop_main();
		}
	}

	public function jump_to_new_event($option, $timestamp, $timeless) {
		list($label,$id,$int_id) = explode('__',$option);
		$callback = DB::GetOne('SELECT handler_callback FROM crm_calendar_custom_events_handlers WHERE id=%d',$id);
		$callback = explode('::', $callback);
		call_user_func($callback, 'new_event', $timestamp, $timeless, $int_id, $this);
/*		if (!is_numeric($timestamp)) $timestamp = strtotime($timestamp);
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('CRM_Calendar','new_event',array($option, $timestamp, $timeless));*/
	}

	public function body($args = array()) {
		$ev_mod = $this->init_module('CRM/Calendar/Event');
		$ev_mod->help('Calendar Help','main');

		if(isset($_REQUEST['search_date']) && is_numeric($_REQUEST['search_date']) && isset($_REQUEST['ev_id']) && is_numeric($_REQUEST['ev_id'])) {
			$default_date = intval($_REQUEST['search_date']);
			$this->view_event(intval($_REQUEST['ev_id']));
		} else
			$default_date = null;


		$handlers = DB::GetAll('SELECT id, group_name, handler_callback FROM crm_calendar_custom_events_handlers');
		$this->lp = $this->init_module('Utils_LeightboxPrompt');
		$count = 0;
		foreach ($handlers as $v) {
			$v['handler_callback'] = explode('::', $v['handler_callback']);
			$new_events = call_user_func($v['handler_callback'], 'new_event_types');
			if ($new_events!==null) foreach($new_events as $k=>$w) {
				if (!is_array($w)) $w = array('label'=>$w, 'icon'=>null);
				$this->lp->add_option('new_event__'.$v['id'].'__'.$k, $w['label'], $w['icon'], null);
				$count++;
			}
		}
		if ($count<2) {
			$this->lp = null;
		} else {
			$this->display_module($this->lp, array(__('New Event'), array('timestamp','timeless'), '', false));
			$vals = $this->lp->export_values();
			if ($vals) {
				$this->jump_to_new_event($vals['option'],$vals['params']['timestamp'],$vals['params']['timeless']);
				return;
			}
		}
		CRM_Calendar_EventCommon::$filter = CRM_FiltersCommon::get();

		$args_defaults = array('default_view'=>Base_User_SettingsCommon::get('CRM_Calendar','default_view'),
			'first_day_of_week'=>Utils_PopupCalendarCommon::get_first_day_of_week(),
			'start_day'=>Base_User_SettingsCommon::get('CRM_Calendar','start_day'),
			'end_day'=>Base_User_SettingsCommon::get('CRM_Calendar','end_day'),
			'interval'=>Base_User_SettingsCommon::get('CRM_Calendar','interval'),
			'default_date'=>$default_date,
			'custom_agenda_cols'=>array(
				array('name'=>__('Type'), 'order'=>'cus_col_0','width'=>6,'wrapmode'=>'nowrap'),
				__('Description'),
				__('Assigned to'),
				__('Related with')
			));
		foreach ($args_defaults as $k=>$v)
			if (!isset($args[$k])) $args[$k] = $args_defaults[$k];

		if (isset($_REQUEST['jump_to_date']) && is_numeric($_REQUEST['jump_to_date']) && isset($_REQUEST['switch_to_tab']) && is_string($_REQUEST['switch_to_tab'])) {
			$args['default_date'] = $_REQUEST['jump_to_date'];
			$args['default_view'] = $_REQUEST['switch_to_tab'];
		}

		$theme = $this->init_module('Base/Theme');
		$c = $this->init_module('Utils/Calendar',array('CRM/Calendar/Event',$args,array($this, 'get_new_event_href_js')));
		$view_type = $c->get_current_view();
		CRM_CalendarCommon::$mode = $view_type;
		$theme->assign('calendar',$this->get_html_of_module($c));
		$theme->display();
		$events = $c->get_displayed_events();
		if (!empty($events['events'])) {
			switch ($view_type) {
				case 'Day': $view = __('Daily agenda'); break;
				case 'Month': $view = __('Monthly agenda'); break;
				case 'Week': $view = __('Weekly agenda'); break;
				case 'Agenda': $view = __('Agenda'); break;
			}
			if (isset($view)) {
				$pdf = $this->pack_module('Libs/TCPDF', 'L');
				if ($pdf->prepare()) {
					set_time_limit(0);
					$start = date('d F Y',Base_RegionalSettingsCommon::reg2time($events['start']));
					$end = date('d F Y',Base_RegionalSettingsCommon::reg2time($events['end']));
					$pdf->set_title($view.', '.$start.($view_type!='Day'?' - '.$end:''));
					$filter = CRM_FiltersCommon::get();
					$me = CRM_ContactsCommon::get_my_record();
					if (trim($filter,'()')==$me['id']) $desc=$me['last_name'].' '.$me['first_name'];
					else $desc = CRM_FiltersCommon::get_profile_desc();
					$pdf->set_subject(__('CRM Filters: %s',array($desc)));
					$pdf->prepare_header();
					$pdf->AddPage();
					foreach($events['events'] as $v) {
						$ev_mod->make_event_PDF($pdf,$v,true,$view_type);
					}
				}
				$pdf->add_actionbar_icon($view);
			}
		}
	}
	
	public function get_new_event_href_js($timestamp, $timeless) {
		if ($this->lp == null) {
			// $this->lp is null only then there's one module providing events with one event type
			$handler = DB::GetRow('SELECT id, group_name, handler_callback FROM crm_calendar_custom_events_handlers');
			if (!$handler) return false;
			$handler['handler_callback'] = explode('::', $handler['handler_callback']);
			$new_events = call_user_func($handler['handler_callback'], 'new_event_types');
			if ($new_events===null || empty($new_events)) return false;
			foreach ($new_events as $k=>$w) {
				if (!is_array($w)) $w = array('label'=>$w, 'icon'=>null);
				if (isset($_REQUEST['create_new_event'])) {
					unset($_REQUEST['create_new_event']);
					$this->jump_to_new_event($_REQUEST['option'],$_REQUEST['timestamp'],$_REQUEST['timeless']);
					return;
				}
				return $this->create_href_js(array('create_new_event'=>true,'option'=>'new_event__'.$handler['id'].'__'.$k, 'timestamp'=>$timestamp, 'timeless'=>$timeless));
			}
		}
		return $this->lp->get_href_js(array($timestamp, $timeless));
	}
	
	public function applet($conf, & $opts) {
		$opts['go'] = true;

		$gb = $this->init_module('Utils/GenericBrowser', null, 'agendaX');
		$columns = array(
			array('name'=>__('Start'), 'order'=>'e.starts', 'width'=>25, 'wrapmode'=>'nowrap'),
			array('name'=>__('Title'), 'order'=>'e.title','width'=>50),
		);
		$gb->set_table_columns($columns);

		$start = date('Y-m-d',time());
		$end = date('Y-m-d',time() + ($conf['days'] * 24 * 60 * 60));

		$gb->set_default_order(array(__('Start')=>'ASC'));
		CRM_Calendar_EventCommon::$filter = '('.CRM_FiltersCommon::get_my_profile().')';
		$data = array();
		Base_ThemeCommon::load_css('CRM_Calendar', 'agenda');

		$custom_events = DB::GetAssoc('SELECT id, handler_callback FROM crm_calendar_custom_events_handlers ORDER BY group_name');
		$ret = array();
		if (!empty($custom_events)) {
			$c = 0;
			foreach ($custom_events as $id=>$cb) {
				if ($conf['events_handlers__'.$id]) {
					$cb = explode('::',$cb);
					if (!is_callable($cb)) continue;
					$add = call_user_func($cb, 'get_all', $start, $end, CRM_Calendar_EventCommon::$filter);
					foreach ($add as $v) {
						$ret[str_pad($v['start'], 16, '0', STR_PAD_LEFT).'__'.$c] = $v;
						$c++;
					}
				}
			}
		}
		
		ksort($ret);

		foreach($ret as $row) {
			if (isset($row['status']) && $row['status']=='closed') continue;
			if (!isset($row['view_action'])) {
				$ex = Utils_CalendarCommon::process_event($row);
				$view_action = '<a '.$this->create_callback_href(array($this,'view_event'),$row['id']).'>';
				$ev_id = explode('_',$row['id'],2);
				$ev_id = $ev_id[0];
			} else {
				$tmp = Utils_CalendarCommon::process_event($row);
				$ex = $row;
				$ex['start'] = $tmp['start'];
				$view_action = '<a '.$row['view_action'].'>';
			}

            //////////////////////////
            // left column
            $date = $ex['start'];
			
            ///////////////////
            // right column
            $title = Utils_TooltipCommon::create($row['title'],$row['custom_tooltip']);
			
			$day = (isset($row['timeless']) && $row['timeless'])?$row['timeless']:Base_RegionalSettingsCommon::time2reg($row['start'], false, true, true, false);
			if ($day<date('Y-m-d')) $class = 'past';
			elseif ($day==date('Y-m-d')) $class = 'today';
			elseif ($day==date('Y-m-d', strtotime('+1 day'))) $class = 'tomorrow';
			else $class = 'other';
			
			$gb_row = $gb->get_new_row();
			$gb_row->set_attrs('class="CRM_Calendar_applet__'.$class.'"');
			$gb_row->add_data(
				array(
					'value'=>$date, 
					'order_value'=>isset($row['timeless'])?strtotime($row['timeless']):$row['start']
				),
				array(
					'value'=>$view_action.$title.'</a>'
				)
			);
		}


		$this->display_module($gb, array(false), 'automatic_display');
	}

	public function view_event($id) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('CRM_Calendar_Event','view',$id);
	}

	public function caption() {
		return __('Calendar');
	}
}
?>
