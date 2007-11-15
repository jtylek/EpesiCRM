<?php
/**
 * CRMCalendar class.
 * 
 * Calendar module with support for managing events.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.99
 * @package tcms-mini
 */

/**
 * status: 0 - open; 1 - in progres; 3 - done; 4 - canceled
 * access: 0 - public; 1 - public, r/o; 2 - private (r/w for creator and related people)
 */
 
defined("_VALID_ACCESS") || die();

class CRM_Calendar_View_Agenda extends Module {
	private $date;
	private $logged;
	private $lang;
	private $agenda_delim;
	
	private function init() {
		$this->logged = (Base_AclCommon::i_am_user() ? Base_UserCommon::get_my_user_id() : -1);
		$this->lang = & $this->init_module('Base/Lang');
		$this->agenda_delim = 8;
		load_js($this->get_module_dir().'/js/Agenda.js');
		$this->logged = Base_UserCommon::get_my_user_id();
		CRM_Calendar_Utils_SidetipCommon::load();
	}
				
	//ADD EVENT
	public function add_event($date, $time = null) {
		$event = & $this->init_module('CRM/Calendar/Event');
		return $event->add_event($date, $time);
	}
	
	public function edit_event($event_type, $event_id) {
		$event = & $this->init_module($event_type);
		return $event->edit_event($event_id);
	}
	public function details_event($event_type, $event_id) {
		$this->pack_module($event_type, array(array('subject'=>$event_id, 'action'=>'details')));
		if($this->is_back()) return false;
		return true;
	}
	public function delete_event($event_type, $event_id) {
		$event = & $this->init_module('CRM/Calendar/Event');
		return $event->delete_event($event_type, $event_id);
	}
	/**
	 * $data = array('year' => int, 'month' => int[1..12], 'day' => int[1..31]) 
	 */
	///////////////////////////////////////////////////////////////////////////
	// agenda events
	// TODO: table layout
	public function menu() {}
	public function show_calendar_agenda() {
		
		$lang = & $this->pack_module("Base/Lang");
		$form = & $this->init_module('Libs/QuickForm',null,"asdfasdfasdfasdf");

		//$d_start = HTML_QuickForm::createElement('datepicker', 'select_start', $lang->t('From'));
		
		//$d_end = HTML_QuickForm::createElement('datepicker', 'select_end', $lang->t('To'));
		//$submit = HTML_QuickForm::createElement('submit', 'submit_button', $lang->ht('Show'));
		
		//$form->addGroup(array($d_start,$d_end, $submit), null, 'Date Range: ', ' - ');
		$form->addElement('datepicker', 'select_start', $lang->t('From'));
		
		$form->addElement('datepicker', 'select_end', $lang->t('To'));
		$form->addElement('submit', 'submit_button', $lang->ht('Show'));
			$form->addRule('select_start', 'Field is required!', 'required');
			//$form->registerRule('proper_date','regex','/^\d{4}\.\d{2}\.\d{2}$/'); 
			//$form->addRule('select_start', 'Invalid date format, must be yyyy.mm.dd', 'proper_date');
			
			$form->addRule('select_end', 'Field is required!', 'required');
			//$form->addRule('select_end', 'Invalid date format, must be yyyy.mm.dd', 'proper_date');
		
		
		//$form->addElement('button', 'submit_button', $lang->t('OK',true), 'onClick="submit_form(\''.$form->getAttribute('name').'\');"');
			
		
		
		if($form->getSubmitValue('submited') && $form->validate()) {
			$data = $form->exportValues();
			$form->setDefaults(array('select_start'=>$data['select_start'], 'select_end'=>$data['select_end']));
			
		} else {
			$today = CRM_Calendar_Utils_FuncCommon::today();
			$delim = CRM_Calendar_Utils_FuncCommon::next_day($today, $this->agenda_delim);
			
			$form_start = sprintf("%d.%02d.%02d", $today['year'],$today['month'],$today['day']);
			$form_end = sprintf("%d.%02d.%02d", $delim['year'],$delim['month'],$delim['day']);
			
			$form->setDefaults(array('select_start'=>time(), 'select_end'=>(time() + (7 * 24 * 60 * 60))));
		}
		$theme =  & $this->pack_module('Base/Theme');
		$form->assign_theme('form', $theme, new HTML_QuickForm_Renderer_TCMSArraySmarty());
		$theme->display();
	
		$datetime_start = $form->getElement('select_start')->getValue();
		$datetime_end = $form->getElement('select_end')->getValue();
			
		//print $datetime_start." ".$datetime_end."<br>";
		$this->retrieve_events($datetime_start, $datetime_end);
	}
	
	public function retrieve_events($datetime_start, $datetime_end) {
		global $database;
		$gb = & $this->init_module('Utils/GenericBrowser', null, 'agenda');
		$columns = array(
			array('name'=>'Date', 'order'=>'datetime_start', 'width'=>1),
			array('name'=>'Begins', 'order'=>'datetime_start', 'width'=>1),
			array('name'=>'Ends', 'order'=>'datetime_end', 'width'=>1),
			array('name'=>'Title', 'order'=>'details')
		);
		$gb->set_table_columns( $columns );
		$types = CRM_Calendar_EventCommon::get_event_types();
		foreach($types as $module) {
			if(method_exists($module['module_name'].'Common', 'get_agenda')) {
				$events = call_user_func(array($module['module_name'].'Common', 'get_agenda'), $datetime_start, $datetime_end);
				
				// regular events
				foreach($events['timeless'] as $event) {
					$ev_id = sprintf('agendaev_%4d%2d%2d0000X%d', $this->date['year'], $this->date['month'], $this->date['day'], $event['id']);
					$row = & $gb->get_new_row();
					$row->add_data(
						date('Y.m.d', strtotime($event['datetime_start'])), 
						'-', 
						'-', 
						'<div id="'.$ev_id.'">'.call_user_func(array($module['module_name'].'Common', 'get_text'), $event, 'agenda').'</div>'
					);
					// details
					if($event['access'] <= 1 || $event['created_by'] == $this->logged)
						$row->add_action( $this->parent->create_callback_href(array($this, 'details_event'), array($module['module_name'], $event['id'])), $this->lang->t('View') );
					if($event['access'] == 0 || $event['created_by'] == $this->logged || $event['uid'] == $this->logged) {
						$row->add_action( $this->create_callback_href(array($this, 'edit_event'), array($module['module_name'], $event['id'])), $this->lang->t('Edit') );
					}
					// delete
					if($event['access'] == 0 || $event['created_by'] == $this->logged || $event['uid'] == $this->logged) {
						$row->add_action( $this->parent->create_confirm_callback_href('Are you sure, you want to delete this event?', array($this, 'delete_event'), array($module['module_name'], $event['id'])), $this->lang->t('Delete') );
					}
					$full = call_user_func(array($module['module_name'].'Common', 'get_text'), $event, 'full');
					CRM_Calendar_Utils_SidetipCommon::create($ev_id, $ev_id, $full, 'agenda');
				}
				
				foreach($events['regular'] as $event) {
					$ev_id = sprintf('agendaev_%4d%2d%2d0000X%d', $this->date['year'], $this->date['month'], $this->date['day'], $event['id']);
					$row = & $gb->get_new_row();
					$row->add_data(
						date('Y.m.d', strtotime($event['datetime_start'])), 
						date('H:i:s', strtotime($event['datetime_start'])), 
						date('H:i:s', strtotime($event['datetime_end'])), 
						'<div id="'.$ev_id.'">'.call_user_func(array($module['module_name'].'Common', 'get_text'), $event, 'agenda').'</div>'
					);
					if($event['access'] == 0 || $event['created_by'] == $this->logged || $event['uid'] == $this->logged) {
						$row->add_action( $this->create_callback_href(array($this, 'edit_event'), array($module['module_name'], $event['id'])), $this->lang->t('Edit') );
					}
					// details
					if($event['access'] <= 1 || $event['created_by'] == $this->logged)
						$row->add_action( $this->parent->create_callback_href(array($this, 'details_event'), array($module['module_name'], $event['id'])), $this->lang->t('View') );
					// delete
					if($event['access'] == 0 || $event['created_by'] == $this->logged || $event['uid'] == $this->logged) {
						$row->add_action( $this->parent->create_confirm_callback_href('Are you sure, you want to delete this event?', array($this, 'delete_event'), array($module['module_name'], $event['id'])), $this->lang->t('Delete') );
					}
					$full = call_user_func(array($module['module_name'].'Common', 'get_text'), $event, 'full');
					CRM_Calendar_Utils_SidetipCommon::create($ev_id, $ev_id, $full, 'agenda');
				}
				
			}
		}
		$this->display_module($gb);
		CRM_Calendar_Utils_SidetipCommon::create_all();
	}
	
	///////////////////////////////////////////////////////////////////////////

	// MENUS ///////////////////////////////////////////////////////////

	////////////////////////////////////////////////////////////////////////
	
	/////////////////////////////////////////////////////////////////////////////
	public function parse_links($date) {
		$action = $this->get_module_variable_or_unique_href_variable('action', '');
		$subject = $this->get_module_variable_or_unique_href_variable('subject', '');
		switch($action) {
			case 'edit':
				$event = & $this->init_module('CRM/Calendar/Event/Personal');
				$this->display_module($event, array(array('action'=>'edit', 'subject'=>$subject)));
				break;
			case 'details':
				$event = & $this->init_module('CRM/Calendar/Event/Personal');
				$this->display_module($event, array(array('action'=>'details', 'subject'=>$subject)));
				break;
			case 'show':
			default:
				$this->show_calendar_agenda($date);
		}
	}
	// Settings ////////////////////////////////////////////////////////////////////////////////////////

	// BODY //////////////////////////////////////////////////////////////////////////////////////////////////////
	
	public function body($arg = null) {
		$this->init();
		if(!isset($arg['date'])) {
			$this->date = $this->get_unique_href_variable('date', CRM_Calendar_Utils_FuncCommon::today());
			if(!is_array($this->date))
				$this->date = CRM_Calendar_Utils_FuncCommon::today();
		} else {
			$this->date = $arg['date'];
		}
		
		
		$this->menu($this->date);
		$this->parse_links($this->date);
		Base_ActionBarCommon::add('add',$this->lang->t('Add Event'), $this->parent->create_callback_href(array($this,'add_event'),array(CRM_Calendar_Utils_FuncCommon::today())));
		
	}
}
?>
