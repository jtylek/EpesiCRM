<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage calendar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

require_once('modules/Base/Theme/bootstrap_icons.php');

class CRM_Calendar extends Module {
	private $lp;
	
	public function new_event($type, $timestamp, $timeless) {
		if ($type!==null) {
			[$label, $id, $int_id] = explode('__',$type);
			$callback = DB::GetOne('SELECT handler_callback FROM crm_calendar_custom_events_handlers WHERE id=%d',$id);
		} else {
			$callback = DB::GetOne('SELECT handler_callback FROM crm_calendar_custom_events_handlers');
		}
		$callback = explode('::', $callback);
		$ret = call_user_func($callback, 'new_event', $timestamp, $timeless, $int_id, null, $this);
		if (!$ret) {
			return Base_BoxCommon::pop_main();
		}
	}

	public function jump_to_new_event($option, $timestamp, $timeless) {
		[$label, $id, $int_id] = explode('__',$option);
		$callback = DB::GetOne('SELECT handler_callback FROM crm_calendar_custom_events_handlers WHERE id=%d',$id);
		$callback = explode('::', $callback);
		call_user_func($callback, 'new_event', $timestamp, $timeless, $int_id, null, $this);
/*		if (!is_numeric($timestamp)) $timestamp = strtotime($timestamp);
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('CRM_Calendar','new_event',array($option, $timestamp, $timeless));*/
	}

	public function body($args = array()) {
		// Needed for the FullCalendar renderer (Utils_Calendar::fullcalendar()): its
		// events come from a JSON fetch, not a server re-render of this module, so the
		// normal get_record_href_array()/create_record_href() "does this href match a
		// pending __jump_to_RB_* request" detection never gets a chance to run for an
		// event click - check_for_jump() performs the same push_module() unconditionally,
		// exactly as Base_Dashboard_0.php:26-27 already does for the same reason. A no-op
		// under the legacy grid engine, since that path's re-render already handles it.
		if (Utils_RecordBrowserInstall::is_installed())
			if (Utils_RecordBrowserCommon::check_for_jump()) return;

		$ev_mod = $this->init_module(CRM_Calendar_Event::module_name());
		$ev_mod->help('Calendar Help','main');

		// True for any render that names a specific view/date to land on -
		// a caller-supplied $args (e.g. the "Full Screen" applet link
		// forcing Agenda view), or either deep-link request below - as
		// opposed to an ordinary render that should just show whatever the
		// user was last looking at. Utils_Calendar::fullcalendar() uses
		// this to decide whether the browser's remembered last-visited
		// view/date is allowed to win; see its 'explicit_navigation'
		// setting.
		$explicit_navigation = isset($args['default_view']) || isset($args['default_date']);

		if(isset($_REQUEST['search_date']) && is_numeric($_REQUEST['search_date']) && isset($_REQUEST['ev_id']) && is_numeric($_REQUEST['ev_id'])) {
			$default_date = intval($_REQUEST['search_date']);
			$this->view_event(intval($_REQUEST['ev_id']));
			$explicit_navigation = true;
		} else
			$default_date = null;


		$handlers = DB::GetAll('SELECT id, group_name, handler_callback FROM crm_calendar_custom_events_handlers');
		$this->lp = $this->init_module('Utils_LeightboxPrompt');
		$count = 0;
		foreach ($handlers as $v) {
			$callback = explode('::', $v['handler_callback']);
			if (!is_callable($callback)) continue;
			$new_events = call_user_func($callback, 'new_event_types');
			if ($new_events!==null) {
				foreach($new_events as $k=>$w) {
					if (!is_array($w)) $w = array('label'=>$w, 'icon'=>null);
					$this->lp->add_option('new_event__'.$v['id'].'__'.$k, $w['label'], $w['icon'], null);
					$count++;
				}
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
			'engine'=>Base_User_SettingsCommon::get('CRM_Calendar','calendar_engine'),
			'first_day_of_week'=>Utils_PopupCalendarCommon::get_first_day_of_week(),
			'start_day'=>Base_User_SettingsCommon::get('CRM_Calendar','start_day'),
			'end_day'=>Base_User_SettingsCommon::get('CRM_Calendar','end_day'),
			'interval'=>Base_User_SettingsCommon::get('CRM_Calendar','interval'),
			'agenda_days'=>Base_User_SettingsCommon::get('CRM_Calendar','agenda_days'),
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
			$explicit_navigation = true;
		}
		$args['explicit_navigation'] = $explicit_navigation;

		// Forwards a click on the FullCalendar toolbar title (Day/Week/Month/
		// Agenda - never Year, which fullcalendar-init.js gives its own
		// native year <select> instead) to a hidden Utils_PopupCalendar
		// trigger printed below - same title_click_forward_selector
		// mechanism Applets_MonthView's embedded mini calendar already uses
		// (see that module's comment). Only meaningful for the FullCalendar
		// engine: the legacy grid renders its own visible popup_calendar
		// per-view already (Utils_Calendar's day()/week()/month()/year()).
		$fc_title_jump = $args['engine'] === 'fullcalendar';
		if ($fc_title_jump) $args['title_click_forward_selector'] = '.epesi-fc-jump a';

		// Applets_MonthView's day-click can ask to land here with the
		// add-event flow already open (one click total, instead of a click to
		// arrive plus a click on "Add event") - reuses get_new_event_href_js()
		// unmodified (same single-handler-vs-Leightbox-picker logic the
		// FullCalendar embed's own double-click-to-add already goes through
		// below), just invoked directly with a real date instead of waiting
		// for a click. $this->lp (if this install has 2+ event handler types)
		// is already set up above.
		if (!empty($_REQUEST['open_add']) && isset($_REQUEST['jump_to_date']) && is_numeric($_REQUEST['jump_to_date'])) {
			$add_href = $this->get_new_event_href_js((int)$_REQUEST['jump_to_date'], true);
			if ($add_href) eval_js($add_href);
		}

		$theme = $this->init_module(Base_Theme::module_name());
		// Registered as a plain static array-callable, not a first-class-callable
		// closure: Module::get_ajax_callback_key() does
		// md5(serialize($func).serialize($args)), and serialize() fatals on a
		// Closure. $args is null (not e.g. the date range) so this mints exactly
		// one $_SESSION['ajax_callbacks'] entry per page render rather than a
		// new one on every calendar navigation - the actual date range travels
		// in the feed URL's query string instead (see fullcalendar-init.js's feed()).
		$events_feed_url = $this->create_ajax_callback_url(array('CRM_CalendarCommon','fullcalendar_events'), null);
		// Same reasoning as $events_feed_url above (static array-callable,
		// $args=null) - drives drag-move/resize via
		// CRM_CalendarCommon::fullcalendar_update().
		$events_write_url = $this->create_ajax_callback_url(array('CRM_CalendarCommon','fullcalendar_update'), null);
		$c = $this->init_module(Utils_Calendar::module_name(),array(CRM_Calendar_Event::module_name(),$args,$this->get_new_event_href_js(...),$events_feed_url,$events_write_url));
		$view_type = $c->get_current_view();
		CRM_CalendarCommon::$mode = $view_type;
		$theme->assign('calendar',$this->get_html_of_module($c));
		$theme->display();

		if ($fc_title_jump) {
			// Same $mount_id formula Utils_Calendar::fullcalendar() derives
			// internally (see that method) - $c->get_path() is stable/
			// deterministic, so this matches regardless of call order.
			// Anchors the popup on the live title element itself (always
			// visible, genuinely where the user just clicked) rather than
			// this trigger's own geometry - the trigger is never shown, only
			// activated programmatically via the title-click forwarding set
			// up above, so a display:none rect would be all-zero.
			$mount_id = 'utils-calendar-fc-'.md5($c->get_path());
			// Single-quoted JS string literal, not json_encode() (which always emits
			// double quotes) - this whole expression is spliced straight into an
			// onClick="..." HTML attribute (PopupCalendarCommon_0.php::create_href()),
			// itself double-quoted, with no further escaping applied there. A
			// double-quoted literal closed that attribute early on its first quote,
			// truncating the handler mid-expression - every FullCalendar view (Day/
			// Week/Month/Agenda) threw "Unexpected end of input" the moment something
			// called .click() on the resulting <a> (fullcalendar-init.js's title-click
			// forwarding). Epesi::escapeJS(..., false, true) escapes the single quotes
			// this literal is wrapped in and leaves any double quotes alone, matching
			// how $link_text below escapes for ITS OWN (double-quoted, but JS-context
			// not HTML-attribute-context) string instead.
			$selector_js = "'".Epesi::escapeJS('#'.$mount_id.' .fc-toolbar-title', false, true)."'";
			// This trigger is one shared Utils_PopupCalendar instance ('calendar_selector'
			// below), reused by every view's title - left at a fixed mode='day' it opened
			// showing whatever grid/level it last happened to be left at (e.g. a decade
			// grid), independent of which view's title was actually clicked. Reset here,
			// on every open, to what that view should offer: Month -> pick a month
			// (mode='month', a page of 12 months - see show_year()'s mode=='month' branch,
			// which finalizes the pick immediately instead of drilling to a day grid);
			// Day/Week/Agenda -> pick a day (mode='day'), centered on the period the user
			// was actually looking at (data-fc-year/month/day, stashed by
			// fullcalendar-init.js's title handler right before it clicks this - `this` is
			// the trigger <a> itself, since this whole $pos_js runs as part of its own
			// onClick). Year view never reaches this trigger at all (fullcalendar-init.js's
			// "info.view.type !== 'multiMonthYear'" guard) - it already has its own native
			// year <select> (applyYearPicker()), left untouched.
			// Single-quoted JS string literals throughout, same reason as $selector_js
			// above: this all lands inside the same double-quoted onClick="..." attribute.
			$mode_reset_js = 'var vt=this.dataset.fcViewType;'.
				'if(typeof datepicker_calendar_selector!==\'undefined\'){'.
					'if(vt===\'dayGridMonth\'){datepicker_calendar_selector.mode=\'month\';datepicker_calendar_selector.show_year(this.dataset.fcYear);}'.
					'else{datepicker_calendar_selector.mode=\'day\';datepicker_calendar_selector.show_month(this.dataset.fcYear,this.dataset.fcMonth,this.dataset.fcDay);}'.
				'}';
			$pos_js = $mode_reset_js.'var t=document.querySelector('.$selector_js.');if(t)jQuery(popup).clonePosition(t,{setWidth:false,setHeight:false,offsetTop:t.offsetHeight});';
			// Raw JS, not a create_unique_href_js() server round trip:
			// FullCalendar's own navigation (prev/next/today, view
			// switching) never reloads the page, so picking a date here
			// calls straight into the live instance the same way -
			// EpesiFullCalendar.gotoDate() (fullcalendar-init.js).
			$link_text = 'EpesiFullCalendar.gotoDate('.json_encode($mount_id).',new Date(__YEAR__,__MONTH__-1,__DAY__))';
			print('<div class="epesi-fc-jump">'.Utils_PopupCalendarCommon::show('calendar_selector', $link_text, 'day', $args['first_day_of_week'], $pos_js, '').'</div>');
		}

		$events = $c->get_displayed_events();
		if (!empty($events['events'])) {
			switch ($view_type) {
				case 'Day': $view = __('Daily agenda'); break;
				case 'Month': $view = __('Monthly agenda'); break;
				case 'Week': $view = __('Weekly agenda'); break;
				case 'Agenda': $view = __('Agenda'); break;
			}
			if (isset($view)) {
				$pdf = $this->pack_module(Libs_TCPDF::module_name(), null, null, 'L');
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
		// Without this, the fullscreen link falls back to body()'s own
		// defaults - the user's saved default_view (whatever that happens
		// to be), not the Agenda list this applet is actually showing.
		// body()'s $explicit_navigation also picks this up, so it correctly
		// takes priority over any remembered last-visited view too.
		$opts['go_function'] = 'body';
		$opts['go_arguments'] = array(array('default_view' => 'agenda'));

		$gb = $this->init_module(Utils_GenericBrowser::module_name(), null, 'agendaX');
		$columns = array(
			// Widths are normalized against their sum by Utils_GenericBrowser
			// (25/75 here), so these are effectively percentages. Start is
			// 'nowrap' and its longest value is a single formatted datetime -
			// give the rest to Title, which is the column that gets truncated.
			array('name'=>__('Start'), 'order'=>'e.starts', 'width'=>25, 'wrapmode'=>'nowrap'),
			array('name'=>__('Title'), 'order'=>'e.title','width'=>75),
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
					// This applet's rows are a merge of every registered handler's
					// events (Meetings, Tasks, Phonecalls, plus whatever a Premium
					// module registered), so the Title column alone doesn't say what
					// kind of thing a row is - prefix each with its own module's
					// sidebar icon. A handler is registered as
					// "<Module>Common::crm_calendar_handler"
					// (CRM_CalendarCommon::new_event_handler()), so the owning module
					// is the callback class minus its "Common" suffix - no second
					// handler->icon registry to keep in sync.
					$bi_icon = Base_BootstrapIcons::type_tag(preg_replace('/Common$/', '', $cb[0]));
					$add = call_user_func($cb, 'get_all', $start, $end, CRM_Calendar_EventCommon::$filter);
					foreach ($add as $v) {
						$v['id'] = $id . '#' . $v['id'];
						$v['bi_icon'] = $bi_icon;
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
				$view_action = '<a '.$this->create_callback_href($this->view_event(...),$row['id']).'>';
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

            // One purifier per request, not one per event row - HTMLPurifier
            // builds its definitions lazily on the first purify(), so a fresh
            // instance per row rebuilds them all every time.
            // See AI-shared/performance-profiling.md (2026-08-31).
            static $purifier = null;
            if ($purifier === null) {
                $config = HTMLPurifier_Config::createDefault();
                $config->set('HTML.AllowedElements','span');
                $purifier = new HTMLPurifier($config);
            }
            $row['title'] = $purifier->purify($row['title']);

            // keep_table=true: the handler's tooltip is a real <table>
            // (Utils_TooltipCommon::format_record_tooltip()), and without
            // this to_safe_html() flattens it back to "Label: value" lines.
            $title = Utils_TooltipCommon::create($row['title'],$row['custom_tooltip'],true,300,true);

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
					'order_value'=> (isset($row['timeless']) && $row['timeless']) ? strtotime($row['timeless']) : $row['start']
				),
				array(
					'value'=>($row['bi_icon'] ?? '').$view_action.$title.'</a>'
				)
			);
		}


		$this->display_module($gb, array(false), 'automatic_display');
	}

	public function view_event($id) {
		Base_BoxCommon::push_module(CRM_Calendar_Event::module_name(),'view',$id);
	}

	public function caption() {
		return __('Calendar');
	}
}
?>
