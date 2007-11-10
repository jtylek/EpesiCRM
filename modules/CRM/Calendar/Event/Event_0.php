<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Calendar_Event extends Module {

	public function add_event($date, $time = null) {
		if($this->is_back())
			return false;
		$lang = & $this->pack_module('Base/Lang');
		$form = & $this->init_module('Libs/QuickForm');
		$event_list = CRM_Calendar_EventCommon::get_event_types();
		if(!empty($event_list)) {
			$event_names = array();
			$event_type = '';
			foreach($event_list as $event) {
				if($event_type === '')
					$event_type = $event['module_name'];
				$event_names[$event['module_name']] = $event['title'];
			}
			$form->addElement('select', 'event_type', $lang->t('Event type').':', $event_names, 'onChange="'.$form->get_submit_form_js(false).'"');
			$form->setDefaults(array('event_type'=>$event_type));
			$event_type = $form->getElement('event_type')->getValue();
			$event_type = $event_type[0];
			// TODO: 
			//  o Should not display form with event types, when event was successfully submited.
			//$form->display();
			
			$event = & $this->init_module($event_type);
			return $event->manage_event('new', array('date'=>$date, 'time'=>$time));
		} else {
			$form->addElement('header', null, 'No event plug-ins installed.<br>In order to add events to your calendar, you must first install at least one plugin from CRM/Calendar/Events/.');
			$form->addElement('button', 'cancel_button', $lang->ht('Back'), $this->create_back_href());
			$form->display();
			return true;
		}
	}
	public function edit_event($event_type, $event_id) {
		if($this->is_back())
			return false;
		
		$event = & $this->init_module($event_type);
		//return $event->edit_event($event_id);
		return $this->display_module($event, array('subject'=>$event_id, 'action'=>'edit'));
	}
	public function details_event($event_type, $event_id) {
		if($this->is_back())
			return false;
		print 'Adding';
		$event = & $this->init_module($event_type);
		return $event->details_event($event_id);
		//return $this->display_module($event, array(array('subject'=>$event_id, 'action'=>'details')));
	}
	public function delete_event($event_type, $event_id) {
		$event = & $this->init_module($event_type);
		return $event->delete_event($event_id);
	}
	public function body($action, $event_type, $event_id) {
		if($this->is_back())
			return false;
			print 'body';
		$event = & $this->init_module($event_type);
		switch($action) {
			case 'edit':
				return $this->display_module($event, array(array('subject'=>$event_id, 'action'=>'details')));
				//return $event->edit_event($event_id);
				break;
			case 'details':
				return $this->display_module($event, array(array('subject'=>$event_id, 'action'=>'details')));
				//return $event->details_event($event_id);
				break;
			default:
				return false;
		}
	}
}

?>
