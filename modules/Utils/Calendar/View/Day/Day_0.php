<?php
/**
 * UtilsCalendar class.
 *
 * Calendar module with support for managing events.
 *
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.99
 * @package tcms-mini
 */
defined("_VALID_ACCESS") || die();

class Utils_Calendar_View_Day extends Module {
	private $lang;
	private $settings;

	public function construct(array $settings) {
		$this->settings = $settings;
		$this->lang = $this->pack_module('Base/Lang');
	}

/*	public function add_event($date, $time = null) {
		$event = & $this->init_module('Utils/Calendar/Event');
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
		$event = & $this->init_module('Utils/Calendar/Event');
		return $event->delete_event($event_type, $event_id);
	}*/


	public function show_calendar_day($date) {

	} //show calendar week

	public function menu($date) {
	
	} // calendar menu

	public function body() {
		$date = array('year'=>date('y'), 'month'=>date('m'), 'day'=>date('d'), 'week'=>date('W'));
		$this->menu($date);
		$this->show_calendar_day($date);
		//Base_ActionBarCommon::add('add',$this->lang->t('Add Event'), $this->create_callback_href(array($this,'add_event'),array($this->date)));

	}
}
?>
