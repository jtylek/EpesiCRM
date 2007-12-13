<?php 
/**
 * Example event module
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package tests-calendar-event
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Calendar_Event extends Utils_Calendar_Event {

	public function view($id) {
		if($this->is_back()) $this->back_to_calendar();
		print('view...');
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
	}

	public function edit($id) {
		if($this->is_back()) $this->back_to_calendar();
		print('edit...');
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
	}

	public function add($def_date,$timeless=false) {
		if($this->is_back()) $this->back_to_calendar();
		
		$qf = $this->init_module('Libs/QuickForm',null,'addf');
		$qf->addElement('datepicker','start','Start date');
		$qf->addElement('datepicker','end','End date');
//		$qf->addElement('checkbox','timeless','Timeless'); //always
		$qf->addElement('text','title','Title');
		$qf->addElement('textarea','description','Description');
		$qf->setDefaults(array('start'=>$def_date,'end'=>$def_date));
		if($qf->validate()) {
			$d = $qf->exportValues();
			DB::Execute('INSERT INTO tests_calendar_event(start,duration,timeless,title,description,created_on,created_by) VALUES(%d,%d,%b,%s,%s,%T,%d)',
				array(strtotime($d['start']),strtotime($d['end'])-strtotime($d['start'])+86400,true,$d['title'],$d['description'],time(),Acl::get_user()));
			$this->back_to_calendar();
		} else {
			$qf->display();
			Base_ActionBarCommon::add('back','Cancel',$this->create_back_href());
			Base_ActionBarCommon::add('save','Save',$qf->get_submit_form_href());
		}
	}

}

?>