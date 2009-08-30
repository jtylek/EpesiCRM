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
	public function body() {
		$ev_mod = $this->init_module('CRM/Calendar/Event');
		$ev_mod->help('Calendar Help','main');

		CRM_CalendarCommon::$trash = $this->get_module_variable('trash',0);
		
		if(CRM_CalendarCommon::$trash) {
			print('<h1>'.$this->t('You are in trash mode').'</h1>');
		}
		
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
				$pdf = $this->pack_module('Libs/TCPDF', 'L');
				if ($pdf->prepare()) {
					$start = date('d F Y',Base_RegionalSettingsCommon::reg2time($events['start']));
					$end = date('d F Y',Base_RegionalSettingsCommon::reg2time($events['end']));
					$pdf->set_title($this->t($view).', '.$start.($view_type!='Day'?' - '.$end:''));
					$filter = CRM_FiltersCommon::get();
					$me = CRM_ContactsCommon::get_my_record();
					if (trim($filter,'()')==$me['id']) $desc=$me['last_name'].' '.$me['first_name'];
					else $desc = CRM_FiltersCommon::get_profile_desc();
					$pdf->set_subject($this->t('CRM Filters: %s',array($desc)));
					$pdf->prepare_header();
					$pdf->AddPage();
					foreach($events['events'] as $v) {
						$ev_mod->make_event_PDF($pdf,$v,true,$view_type);
					}
				}
				$pdf->add_actionbar_icon($this->t(str_replace(' ','_',$view)));
			}
		}
//		if(Base_AclCommon::i_am_sa()) {
			if(CRM_CalendarCommon::$trash)
				Base_ActionBarCommon::add(Base_ThemeCommon::get_template_file('CRM_Calendar','icon.png'),'Calendar', $this->create_callback_href(array($this, 'set_trash'),array(0)));		
			else
				Base_ActionBarCommon::add('delete','Trash', $this->create_callback_href(array($this, 'set_trash'),array(1)));
//		}
	}
	
	public function set_trash($v) {
		$this->set_module_variable('trash',$v);
	}

	public function applet($conf,$opts) {
		$opts['go'] = true;

		$gb = $this->init_module('Utils/GenericBrowser', null, 'agendaX');
		$columns = array(
			array('name'=>$this->t('Start'), 'order'=>'e.starts', 'width'=>50),
			array('name'=>$this->t('Title'), 'order'=>'e.title','width'=>50),
		);
		$gb->set_table_columns($columns);

		$start = date('Y-m-d',time());
		$end = date('Y-m-d',time() + ($conf['days'] * 24 * 60 * 60));

		$gb->set_default_order(array($this->t('Start')=>'ASC'));
		CRM_Calendar_EventCommon::$filter = '('.CRM_FiltersCommon::get_my_profile().')';
//		trigger_error($gb->get_query_order());
		$ret = CRM_Calendar_EventCommon::get_all($start,$end);
		$data = array();
		$colors = CRM_Calendar_EventCommon::get_available_colors();
		foreach($ret as $row) {
			if ($row['status']>=2) continue;
			if ($conf['color']!=0 && $colors[$conf['color']]!=$row['color']) continue;
			$ex = Utils_CalendarCommon::process_event($row);
			$view_action = '<a '.$this->create_callback_href(array($this,'view_event'),$row['id']).'>';
			
			$ev_id = explode('_',$row['id'],2);
			$ev_id = $ev_id[0];
			
            ///////////////////
            // right column
            $title = Utils_TooltipCommon::create($row['title'],$row['description']);
			
            //////////////////////////
            // left column
            $date = Utils_TooltipCommon::create($ex['start'],$row['custom_tooltip']);

            $gb->add_row(
                array('value'=>$view_action.$date.'</a>', 'order_value'=>isset($row['timeless'])?strtotime($row['timeless']):$row['start']),
                $view_action.$title.'</a>');			
		}


		$this->display_module($gb, array(false), 'automatic_display');
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
