<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar extends Module {
	private $lang;

	public function body() {
		CRM_Calendar_EventCommon::$filter = CRM_FiltersCommon::get();
		CRM_FiltersCommon::add_action_bar_icon();
		
		if(isset($_REQUEST['search_date']) && is_numeric($_REQUEST['search_date']) && isset($_REQUEST['ev_id']) && is_numeric($_REQUEST['ev_id'])) {
			$default_date = intval($_REQUEST['search_date']);
			$this->view_event(intval($_REQUEST['ev_id']));
		} else
			$default_date = null;

		$args = array('default_view'=>Base_User_SettingsCommon::get('CRM_Calendar','default_view'),
			'first_day_of_week'=>Utils_PopupCalendarCommon::get_first_day_of_week(),
			'start_day'=>Base_User_SettingsCommon::get('CRM_Calendar','start_day'),
			'end_day'=>Base_User_SettingsCommon::get('CRM_Calendar','end_day'),
			'interval'=>Base_User_SettingsCommon::get('CRM_Calendar','interval'),
			'default_date'=>$default_date,
			'custom_agenda_cols'=>array('Description','Assigned to','Related with')
			);
		
		if (isset($_REQUEST['jump_to_date']) && is_numeric($_REQUEST['jump_to_date']) && isset($_REQUEST['switch_to_tab']) && is_string($_REQUEST['switch_to_tab'])) {
			$args['default_date'] = $_REQUEST['jump_to_date'];
			$args['default_view'] = $_REQUEST['switch_to_tab'];
		}
		
		$theme = $this->init_module('Base/Theme');
		$c = $this->init_module('Utils/Calendar',array('CRM/Calendar/Event',$args));
		$theme->assign('calendar',$this->get_html_of_module($c));
		$theme->display();
		$events = $c->get_displayed_events();
		if (!empty($events['events'])) {
			$view_type = $c->get_current_view();
			switch ($view_type) {
				case 'Day': $view = 'Daily agenda'; break;
				case 'Month': $view = 'Monthly agenda'; break;
				case 'Week': $view = 'Weekly agenda'; break;
				case 'Agenda': $view = 'Agenda'; break;
			}
			if (isset($view)) {
				$this->lang = $this->init_module('Base/Lang');
				$pdf = $this->pack_module('Libs/TCPDF', 'L');
				if ($pdf->prepare()) {
					$ev_mod = $this->init_module('CRM/Calendar/Event');
					$start = date('d F Y',$events['start']);
					$end = date('d F Y',$events['end']);
					$pdf->set_title($this->lang->t($view).', '.$start.($view_type!='Day'?' - '.$end:''));
					$filter = CRM_FiltersCommon::get();
					$me = CRM_ContactsCommon::get_my_record();
					if (trim($filter,'()')==$me['id']) $desc=$me['last_name'].' '.$me['first_name'];
					else $desc = CRM_FiltersCommon::get_profile_desc();
					$pdf->set_subject($this->lang->t('CRM Filters: %s',array($desc)));
					$pdf->prepare_header();
					$pdf->AddPage();
					foreach($events['events'] as $v) {
						$ev_mod->make_event_PDF($pdf,$v['id'],true,$view_type);
					}
				}
				$pdf->add_actionbar_icon($this->lang->t(str_replace(' ','_',$view)));
			}
		}
	}
	
	public function applet($conf,$opts) {
		$opts['go'] = true;

		$l = $this->init_module('Base/Lang');

		$gb = $this->init_module('Utils/GenericBrowser', null, 'agenda');
		$columns = array(
			array('name'=>$l->t('Start'), 'order'=>'e.start', 'width'=>50),
			array('name'=>$l->t('Title'), 'order'=>'e.title','width'=>50),
		);
		$gb->set_table_columns($columns);

		$start = time();
		$end = $start + ($conf['days'] * 24 * 60 * 60);

		$gb->set_default_order(array($l->t('Start')=>'ASC'));
		CRM_Calendar_EventCommon::$filter = '('.CRM_FiltersCommon::get_my_profile().')';
		$ret = CRM_Calendar_EventCommon::get_all($start,$end,$gb->get_query_order());
		$data = array();
		$colors = CRM_Calendar_EventCommon::get_available_colors();
		foreach($ret as $row) {
			if ($conf['color']!=0 && $colors[$conf['color']]!=$row['color']) continue; 
			$ex = Utils_CalendarCommon::process_event($row);
			$view_action = '<a '.$this->create_callback_href(array($this,'view_event'),$row['id']).'>';
			if($row['description'])
				$title = Utils_TooltipCommon::create($row['title'],$row['description']);
			else
				$title = $row['title'];
			$gb->add_row($view_action.(isset($ev['timeless']) && $ev['timeless']?Utils_TooltipCommon::create($ex['start'],$l->t('Duration: %s<br>End: %s',array($ex['duration'],$ex['end']))):$ex['end']).'</a>',$view_action.$title.'</a>');
		}

		$this->display_module($gb);
	}
	
	public function view_event($id) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('CRM_Calendar_Event','view',$id);
	}

	public function caption() {
		return "Calendar";
	}
}
?>
