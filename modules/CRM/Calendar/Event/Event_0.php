<?php
/**
 * Calendar event module
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage calendar-event
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_Event extends Utils_Calendar_Event {
	private $custom_defaults = array();
	private static $access;
	private static $priority;

	public function construct() {
		self::$access = Utils_CommonDataCommon::get_translated_array('CRM/Access');
		self::$priority = Utils_CommonDataCommon::get_translated_array('CRM/Priority');
	}


	public function view_event($action, $id) {
		$check = explode('#', $id);
		if (isset($check[1])) {
			$callback = DB::GetOne('SELECT handler_callback FROM crm_calendar_custom_events_handlers WHERE id=%d', $check[0]);
			$callback = explode('::', $callback);
			$ev = call_user_func($callback, $action.'_event', $check[1], $this);
		} else {
			trigger_error('Invalid event id: '.$id, E_USER_ERROR);
		}
	}

	public function add($def_date,$timeless=false) {
	
	}

	public function view($id) {
//		if($this->is_back()) $this->back_to_calendar();
		$this->view_event('view', $id);
	}

	public function edit($id) {
//		if($this->is_back()) $this->back_to_calendar();
		$this->view_event('edit',$id);
	}

	public function make_event_PDF($pdf, $id, $no_details = false,$type='Event'){
		$custom_event = false;
		if (!is_array($id)) {
			$check = explode('#', $id);
			if (isset($check[1])) {
				$callback = DB::GetOne('SELECT handler_callback FROM crm_calendar_custom_events_handlers WHERE id=%d', $check[0]);
				$callback = explode('::', $callback);
				$ev = call_user_func($callback, 'get', $check[1]);
				$no_details = true;
				$custom_event = true;
			} else {
				trigger_error('Invalid event id: '.$id, E_USER_ERROR);
			}
		} else {
			$ev = $id;
			$id = $ev['id'];
			$id = explode('_',$id);
			$id = $id[0];
/*			$ev_details = DB::GetRow('SELECT *, starts AS start, ends AS end FROM crm_calendar_event WHERE id=%d', array($id));
			foreach ($ev_details as $k=>$v)
				if (!isset($ev[$k])) $ev[$k] = $v;*/
			$ev['title'] = strip_tags($ev['title']);
			$check = explode('#', $id);
			if (isset($check[1])) {
				$no_details = true;
				$custom_event = true;
			}
		}
		$pdf_theme = $this->pack_module('Base/Theme');
		$pdf_theme->assign('description', array('label'=>__('Description'), 'value'=>str_replace("\n",'<br/>',htmlspecialchars($ev['description']))));
		if (!$no_details) {
			$ev['status'] = Utils_CommonDataCommon::get_value('CRM/Status/'.$ev['status'],true);
			$ev['access'] = self::$access[$ev['access']];
			$ev['priority'] = self::$priority[$ev['priority']];
			foreach (array('access'=>__('Access'), 'priority'=>__('Priority'), 'status'=>__('Status')) as $v=>$label)
				$pdf_theme->assign($v, array('label'=>$label, 'value'=>$ev[$v]));
			$created_by = CRM_ContactsCommon::get_contact_by_user_id($ev['created_by']);
			if ($created_by!==null) $created_by = $created_by['last_name'].' '.$created_by['first_name'];
			else $created_by = Base_UserCommon::get_user_login($ev['created_by']);
			$created_on = Base_RegionalSettingsCommon::time2reg($ev['created_on'],false);
			$pdf_theme->assign('created_on', array('label'=>__('Created on'), 'value'=>$created_on));
			$pdf_theme->assign('created_by', array('label'=>__('Created by'), 'value'=>$created_by));
			if ($ev['edited_by']!=null) {
				$edited_by = CRM_ContactsCommon::get_contact_by_user_id($ev['edited_by']);
				if ($edited_by!==null) $edited_by = $edited_by['last_name'].' '.$edited_by['first_name'];
				else $edited_by = Base_UserCommon::get_user_login($ev['edited_by']);
				$edited_on = Base_RegionalSettingsCommon::time2reg($ev['edited_on'],false);
			} else {
				$edited_by = '--';
				$edited_on = '--';
			}
			$pdf_theme->assign('edited_on', array('label'=>__('Edited on'), 'value'=>$edited_on));
			$pdf_theme->assign('edited_by', array('label'=>__('Edited by'), 'value'=>$edited_by));
			$pdf_theme->assign('printed_on', array(	'label'=>__('Printed on'),
													'value'=>Base_RegionalSettingsCommon::time2reg(time())));
		}
		$emps = array();
		$cuss = array();
		$cus_cmps = array();
		if (isset($ev['employees']) && !empty($ev['employees'])) {
			foreach ($ev['employees'] as $v) {
				$c = CRM_ContactsCommon::get_contact($v);
				$emps[] = array('name'=>$c['last_name'].' '.$c['first_name'],
								'mphone'=>$c['mobile_phone'],
								'wphone'=>$c['work_phone'],
								'hphone'=>$c['home_phone']);
			}
		}
		if (isset($ev['customers']) && !empty($ev['customers'])) {
			foreach ($ev['customers'] as $v) {
				$det = explode(':', $v);
				if (isset($det[1])) $v = $det[1];
				else {
					$v = $det[0];
					$det[0] = 'P';
				}
				if ($det[0]=='P') {
					$c = CRM_ContactsCommon::get_contact($v);
					$company_name = isset($c['company_name']) && is_numeric($c['company_name'])
                            ? array(Utils_RecordBrowserCommon::get_value('company', $c['company_name'], 'Company Name'))
                            : '---';
                    $cuss[] = array('name'=>$c['last_name'].' '.$c['first_name'],
									'mphone'=>$c['mobile_phone'],
									'wphone'=>$c['work_phone'],
									'hphone'=>$c['home_phone'],
									'company_name'=>$company_name);
				}
				if ($det[0]=='C') $c = array('company_name'=>array($v));
				if (is_array($c['company_name']))
					foreach ($c['company_name'] as $v2)
						if (!isset($cus_cmps[$v2]))
							$cus_cmps[$v2] = CRM_ContactsCommon::get_company($v2);
				
			}
		}
		$pdf_theme->assign('employees', array(	'main_label'=>__('Employees'),
												'name_label'=>__('Name'),
												'mphone_label'=>__('Mobile Phone'),
												'wphone_label'=>__('Work Phone'),
												'hphone_label'=>__('Home Phone'),
												'lp_label'=>__('Lp'),
												'data'=>$emps
												));
		$pdf_theme->assign('customers', array(	'main_label'=>__('Customers'),
												'name_label'=>__('Name'),
												'mphone_label'=>__('Mobile Phone'),
												'wphone_label'=>__('Work Phone'),
												'hphone_label'=>__('Home Phone'),
												'company_name'=>__('Company Name'),
												'lp_label'=>__('Lp'),
												'data'=>$cuss
												));
		$pdf_theme->assign('customers_companies', array(	'main_label'=>__('Customers Companies'),
															'name_label'=>__('Company Name'),
															'phone_label'=>__('Phone'),
															'fax_label'=>__('Fax'),
															'address_label'=>__('Address'),
															'city_label'=>__('City'),
															'lp_label'=>__('Lp'),
															'data'=>$cus_cmps
															));
		$pdf_theme->assign('title', array(	'label'=>__('Title'),
											'value'=>$ev['title']));
		$start = Base_RegionalSettingsCommon::time2reg($ev['start'],false);
		$pdf_theme->assign('start_date', array(	'label'=>__('Start Date'),
												'value'=>$start,
												'details'=>array('weekday'=>__date('l', strtotime($start)))));
		if (!isset($ev['timeless'])) {
			$pdf_theme->assign('start_time', array(	'label'=>__('Start Time'),
													'value'=>Base_RegionalSettingsCommon::time2reg($ev['start'],true,false)));
			if (!isset($ev['end'])) trigger_error(print_r($ev,true));
			$pdf_theme->assign('end_time', array(	'label'=>__('End Time'),
													'value'=>Base_RegionalSettingsCommon::time2reg($ev['end'],true,false)));
			$hours = floor(($ev['end']-$ev['start'])/3600);
			$format = __('%d hours', array($hours));
			$minutes = ($ev['end']-$ev['start'])%3600;
			if ($minutes!=0) {
				if ($hours==0) $format = '';
				else $format .= ', ';
				$format .= __('%d minutes', array($minutes/60));
			}
			$pdf_theme->assign('duration', array(	'label'=>__('Duration'),
													'value'=>$format));
			if (date('Y-m-d',$ev['start'])!=date('Y-m-d',$ev['end']))
				$pdf_theme->assign('end_date', array(	'label'=>__('End Date'),
														'value'=>Base_RegionalSettingsCommon::time2reg($ev['end'],false)));
		} else $pdf_theme->assign('timeless', array(	'label'=>__('Timeless'),
														'value'=>__('Yes')));
		$pdf_theme->assign('type',$type);
		ob_start();
		$pdf_theme->display('pdf_version');
		$cont = ob_get_clean();
		$pdf->writeHTML($cont);
	}
	
	public function get_navigation_bar_additions() {
		$custom_handlers = CRM_CalendarCommon::get_event_handlers();
		if (empty($custom_handlers)) return '';
		$form = $this->init_module('Libs/QuickForm');

		$elements_name = array();
		$default = array();
		foreach ($custom_handlers as $k=>$v) {
			$form->addElement('checkbox', 'events_handlers__'.$k, $v, null, array('onclick'=>'calendar_event_handlers_changed=1;'));
			$elements_name[$k] = 'events_handlers__'.$k;
			$default[] = $k;
		}
		$form->addElement('hidden', 'event_handlers_changed', '', array('id'=>'event_handlers_changed'));
		eval_js('calendar_event_handlers_changed=0;');
		eval_js('hide_calendar_event_handlers_popup = function() {'.
			'if(var_hide_calendar_event_handlers_popup==1){'.
				'$("calendar_event_handlers_popup").style.display="none";'.
				'$("calendar_event_handlers_trigger").innerHTML=calendar_event_handlers_message_default;'.
				'if(calendar_event_handlers_changed==1){'.
					$form->get_submit_form_js().
					'$("calendar_event_handlers_trigger").innerHTML=calendar_event_handlers_message_processing;'.
				'}'.
			'}'.
		'}');
		eval_js('show_calendar_event_handlers_popup = function() {'.
			'$("calendar_event_handlers_popup").style.display="";'.
			'$("calendar_event_handlers_trigger").innerHTML=calendar_event_handlers_message_confirm;'.
		'}');

		$selected = $this->get_module_variable('events_handlers', $default);
		if ($form->validate()) {
			$vals = $form->exportValues();
			$selected = array();
			foreach ($elements_name as $k=>$e)
				if (isset($vals[$e]) && $vals[$e]) $selected[] = $k;
			$this->set_module_variable('events_handlers', $selected);
		}
		CRM_Calendar_EventCommon::$events_handlers = $selected;

		foreach ($selected as $k=>$e) {
		    if(isset($elements_name[$e]))
    			$form->setDefaults(array($elements_name[$e]=>true));
		}
		$label = 'Filter: Error';
		$select_count = count($selected);
		if ($select_count==count($custom_handlers)) $label = __('All');
		else $label = __('Selection (%d)',array($select_count));
		if ($select_count==1) $label = $custom_handlers[reset($selected)];
		if ($select_count==0) $label = __('None');

		$theme = $this->init_module('Base/Theme');
		$theme->assign('elements_name', $elements_name);
		$theme->assign('label', $label);

		eval_js('calendar_event_handlers_message_default="'.$label.'";');
		eval_js('calendar_event_handlers_message_processing="'.__('Processing...').'";');
		eval_js('calendar_event_handlers_message_confirm="'.__('Save selection').'";');

		$form->assign_theme('form', $theme);
		ob_start();
		$theme->display('custom_event_handlers_form');
		$handlers_form = ob_get_clean();

		return $handlers_form;
	}
}

?>
