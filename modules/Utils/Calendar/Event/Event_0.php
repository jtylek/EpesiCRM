<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

require_once('adodb/adodb-active-record.inc.php');
ADODB_Active_Record::SetDatabaseAdapter(DB::$ado);

class CRM_Calendar_Event_Record extends ADODB_Active_Record {
	var $_table = 'calendar_events';

}

class CRM_Calendar_Event extends Module {

	public $lang;

	public function construct() {
		$this->lang = & $this->init_module('Base/Lang');
	}

	public function add_event_submit($d) {

		if(!isset($d['timeless']))
			$d['timeless'] = 0;

		$datetime_start;
		$datetime_end;
		$d['date_s'] = Base_RegionalSettingsCommon::server_date($d['date_s']);
		$d['date_e'] = Base_RegionalSettingsCommon::server_date($d['date_e']);
		if($d['timeless'] == 0){
			if(Base_RegionalSettingsCommon::time_12h()) {
				if($d['time_s']['a'] == 'pm')
					$d['time_s']['h'] += 12;
				if($d['time_e']['a'] == 'pm')
					$d['time_e']['h'] += 12;
				$d['time_s']['i'] = sprintf("%02d", $d['time_s']['i']);
				$d['time_e']['i'] = sprintf("%02d", $d['time_e']['i']);
				$dt_start = 	$d['date_s']." ".$d['time_s']['h'].":".$d['time_s']['i'].":00";
				$dt_end = 		$d['date_e']." ".$d['time_e']['h'].":".$d['time_e']['i'].":00";
			} else {
				$dt_start = 	$d['date_s']." ".$d['time_s']['H'].":".$d['time_s']['i'].":00";
				$dt_end = 		$d['date_e']." ".$d['time_e']['H'].":".$d['time_e']['i'].":00";
			}
		} else {
			$dt_start = 	$d['date_s']." "."00:00".":00";
			$dt_end = 		$d['date_e']." "."23:59".":59";
		}
		// adding participants group

		//print $dt_start.' '.$dt_end.'<br>';
		//return false;
		/*
		$d['emp_id'] = explode('__SEP__',$d['emp_id']);
        array_shift($d['emp_id']);
		$d['cus_id'] = explode('__SEP__',$d['cus_id']);
        array_shift($d['cus_id']);
        */

		// JTylek - Store an array here
		/*
		DB::Execute("INSERT INTO calendar_event_personal_gid_counter(something) VALUES(1)");
		$emp_gid = DB::Insert_ID('calendar_event_personal_gid_counter', 'id');
		foreach( $d['emp_id'] as $key=>$val) {
			DB::Execute("insert into calendar_event_personal_group(gid, uid) values(%d, %d)", array($emp_gid, $val));
		}

		DB::Execute("INSERT INTO calendar_event_personal_gid_counter(something) VALUES(1)");
		$cus_gid = DB::Insert_ID('calendar_event_personal_gid_counter', 'id');
		foreach( $d['cus_id'] as $key=>$val) {
			DB::Execute("insert into calendar_event_personal_group(gid, uid) values(%d, %d)", array($cus_gid, $val));
		}
		*/

		//DB::Execute("insert into calendar_event_personal(title,    act_id,       emp_gid,       description,       datetime_start, datetime_end, timeless,       priority,       access,       status, created_on, created_by, edited_on, edited_by) ".
		//										"values(%s,        %d,           %d,            %s,                %d,             %d,           %d,             %d,             %d,           %s, %d, '%s', %d, '%s', %d)",
		//										array($d['title'], $d['act_id'], $d['emp_gid'], $d['description'], $dt_start,      $dt_end,      $d['timeless'], $d['priority'], $d['access'], $d['status'], Acl::get_user(), date("Y.m.d H:i"), Acl::get_user(), date("Y.m.d H:i"), $data['timeless'])
		//);
		$record = new CRM_Calendar_Event_Record();
		$record->title = $d['title'];
		$record->act_id = $d['act_id'];
		$record->employee = $employees;
		$record->contact = $contacts;

		$record->description = $d['description'];
		$record->datetime_start = $dt_start;
		$record->datetime_end = $dt_end;
		$record->timeless = $d['timeless'];
		$record->priority = $d['act_id'];
		$record->access = $d['access'];
		$record->status = 1;
		$record->created_on = date("Y.m.d H:i");
		$record->created_by = Acl::get_user();
		$record->edited_on = date("Y.m.d H:i");
		$record->edited_by = Acl::get_user();
		$record->save();
		return true;
	}

	public function edit_event_submit($d) {

		if(!isset($d['timeless']))
			$d['timeless'] = 0;

		$datetime_start;
		$datetime_end;

		$d['date_s'] = Base_RegionalSettingsCommon::server_date($d['date_s']);
		$d['date_e'] = Base_RegionalSettingsCommon::server_date($d['date_e']);
		if($d['timeless'] == 0){
			if(Base_RegionalSettingsCommon::time_12h()) {
				if($d['time_s']['a'] == 'pm')
					$d['time_s']['h'] += 12;
				if($d['time_e']['a'] == 'pm')
					$d['time_e']['h'] += 12;
				$dt_start = 	$d['date_s']." ".$d['time_s']['h'].":".$d['time_s']['i'].":00";
				$dt_end = 		$d['date_e']." ".$d['time_e']['h'].":".$d['time_e']['i'].":00";
			} else {
				$dt_start = 	$d['date_s']." ".$d['time_s']['H'].":".$d['time_s']['i'].":00";
				$dt_end = 		$d['date_e']." ".$d['time_e']['H'].":".$d['time_e']['i'].":00";
			}
		} else {
			$dt_start = 	$d['date_s']." "."00:00".":00";
			$dt_end = 		$d['date_e']." "."23:59".":59";
		}
		// adding participants group

		// DB::Execute("delete from calendar_event_personal_group where gid=", array($gid));
		//$d['emp_id'] = explode('__SEP__',$d['emp_id']);
        //array_shift($d['emp_id']);

		/*
		foreach( $d['emp_id'] as $key=>$val) {
			DB::Execute("insert into calendar_event_personal_group(gid, uid) values(%d, %d)", array($gid, $val));
		}
		*/


		DB::Execute('update calendar_events set '.
				'title=%s, '.
				'act_id=%d, '.
				'employee=%d, '.
				'contact=%d, '.
				'description=%s, '.
				'datetime_start=%T, '.
				'datetime_end=%T, '.
				'timeless=%d, '.
				'priority=%d, '.
				'access=%d,  '.
				'status=%d, '.
				'edited_on=%T, '.
				'edited_by=%d '.
			'where id=%d',
			array($d['title'], $d['act_id'], $gid, $d['description'], $dt_start, $dt_end, $d['timeless'], $d['priority'], $d['access'], 1, date("Y.m.d H:i"), Acl::get_user(), $d['id'])
		);
		return true;
	}

	///////////////////////////////////////////////////////////////////////////////////////////////////
	public function manage_event($action = 'new', array $options = array()) {
		if($this->is_back())
			return false;

		//print 'MANAGE';
		$def = array('action'=>$action);
		$subject = -1;
		// NEW EVENT:
		if($action == 'new') {
			//print 'new';
			////////////////////////////////////////////////////////////////////////////
			// drawing form
			$repeatable = 0;
			$repeat_forever = 0;
			$def = array(
				'date_s' => date("Y-m-d"),
				'date_e' => time(),//date("Y.m.d"),
				'time_s' => date("H:i"),
				'time_e' => date("H:i"),
				'repeatable'=>0, 'repeat_forever'=>1, 'access'=>0,	'priority'=>0
			);
			if($options['date']) {
				$date = $options['date'];
				$year = $date['year']; $month = $date['month']; $day = $date['day'];
				$t = Base_RegionalSettingsCommon::server_date($year.'-'.$month.'-'.$day);
				$def['date_s'] = $t;
				$def['date_e'] = $t;
			}
			if($options['time']) {
				$def['time_s'] = $options['time']['hour'].':'.$options['time']['minute'];
				$def['time_e'] = (($options['time']['hour']+1)%24).':'.$options['time']['minute'];
			} else {
				$def['timeless'] = 1;
			}
			$def['emp_id'] = array(Acl::get_user());
		// EDIT / PREVIEW:
		} else if($action == 'edit' || $action == 'details') {
			//print 'edit';
			$subject = $options['subject'];
			$set = DB::Execute("select * from calendar_events where id=%d", $subject);
			$event = array();
			if($set)
				if($row = $set->FetchRow())
					$event = $row;
			$def = array(
				//'date_s' => str_replace('-', '.', substr($event['datetime_start'], 0, 10)),
				'date_s' => Base_RegionalSettingsCommon::server_date(substr($event['datetime_start'], 0, 10)),
				//'date_e' => str_replace('-', '.', substr($event['datetime_end'], 0, 10)),
				'date_e' => Base_RegionalSettingsCommon::server_date(substr($event['datetime_end'], 0, 10)),
				'time_s' => str_replace('-', '.', substr($event['datetime_start'], 11, 5)),
				'time_e' => str_replace('-', '.', substr($event['datetime_end'], 11, 5)),
				'title'=>$event['title'],
				'description'=>$event['description'],
				'priority'=>$event['priority'],
				'timeless'=>$event['timeless'],
				'access'=>$event['access'],
				'act_id'=>array($event['act_id']),
				'emp_gid'=>$event['emp_gid'],
				'created' => 'by '.Base_UserCommon::get_user_login($event['created_by'])." on ".$event['created_on'],
				'edited' => 'by '.Base_UserCommon::get_user_login($event['edited_by'])." on ".$event['edited_on']
			);
			$def_emps = array();
			/*$set = DB::Execute("select uid from calendar_events where gid=%d", $event['emp_gid']);
			if($set )
				while($row_grp = $set->FetchRow()) {
					array_push($def_emps, $row_grp['uid']);
				}
			$def['emp_id'] = $def_emps;
			*/

		$timeless = $event['timeless'];
		}


		// begining
		$lang = & $this->pack_module('Base/Lang');
		$form = & $this->init_module('Libs/QuickForm');
		if($action == 'edit' || $action == 'details')
			$form->addElement('hidden', 'id', $subject);

		$lang = & $this->pack_module('Base/Lang');
		$com = array();
		//$ret = CRM_CompaniesCommon::get_companies();
		//if(!empty($ret))
		//	foreach($ret as $id=>$data) {
		//		$com[$id] = $data['name'];
		//	}
		$timeless = 0;
		//////////////////////////////////////////////////////////////////////////
		// getting data...
		$emp = array();
		$ret = CRM_ContactsCommon::get_contacts(array('Company Name'=>CRM_ContactsCommon::get_main_company()));
		foreach($ret as $id=>$data) {
			$emp[$id] = $data['Last Name']. " " .$data['First Name'];
		}
		$cus = array();
		$ret = CRM_ContactsCommon::get_contacts(array('!Company Name'=>CRM_ContactsCommon::get_main_company()));
		foreach($ret as $id=>$data) {
			$cus[$id] = $data['Last Name']. " " .$data['First Name'];
		}

		$act = array();
		//$ret = DB::Execute("select id, name from calendar_events order by name");
		$ret = DB::Execute("select id, name from calendar_event_types order by name");
		while($row = $ret->FetchRow()) {
			$act[$row['id']] = $row['name'];
		}

		$access = array(0=>'public', 1=>'public, read-only', 2=>'private');
		$priority = array(0=>'low', 1=>'medium', 2=>'high');

		////////////////////////////////////////////////////////////////////////////////////
		// BUILD FORM
		$form->addElement('header', null, $lang->t('Beginning of event'));
		$asm = $form->addElement('text', 'title', $lang->t('Title'), array('style'=>'width: 100%;'));
			$form->addRule('title', 'Field is required!', 'required');

		//start
		$asm = $form->addElement('datepicker', 'date_s', $lang->t('Event start'));
			$form->addRule('date_s', 'Field is required!', 'required');
			//$form->registerRule('proper_date','regex','/^\d{4}\.\d{2}\.\d{2}$/');
			//$form->addRule('date_e', 'Invalid date format, must be yyyy.mm.dd', 'proper_date');
		if(Base_RegionalSettingsCommon::time_12h())
			$form->addElement('date', 'time_s', $lang->t('Time'), array('format'=>'h:i:a'));
		else
			$form->addElement('date', 'time_s', $lang->t('Time'), array('format'=>'H:i'));

		// fin
		$form->addElement('header', null, $lang->t('Ending of event'));
		$form->addElement('datepicker', 'date_e', $lang->t('Event end'));
			$form->addRule('date_e', 'Field is required!', 'required');
			//$form->addRule('date_e', 'Invalid date format, must be yyyy.mm.dd', 'proper_date');
			//$form->addRule(array('date_e', 'date_s'), 'End date must be after begin date...', 'compare', 'gte');
		if(Base_RegionalSettingsCommon::time_12h())
			$form->addElement('date', 'time_e', $lang->t('Time'), array('format'=>'h:i:a'));
		else
			$form->addElement('date', 'time_e', $lang->t('Time'), array('format'=>'H:i'));

		// all day event?
		$form->addElement('checkbox', 'timeless', $lang->t('Lasts whole day?'), null,'onClick="'.$form->get_submit_form_js(false).'"');
		switch ($form->getElement('timeless')->getValue()) {
			case '1':
				$timeless = 1;
				break;
			case '0':
			default:
				$timeless = 0;
				break;
		}

		// event doer
		$form->addElement('header', null, $lang->t('Event itself'));
		$size = 8;

		$form->addElement('select', 'rel_com_id', $lang->t('Company'), $com, array('style'=>'width: 100%;'));
		// event type

		// event type
		$form->addElement('select', 'act_id', $lang->t('Activity'), $act, array('style'=>'width: 100%;'));
		// event access
		//$form->addElement('select', 'access', $lang->t('Access'), $access, array('style'=>'width: 100%;'));
		$form->addElement('select', 'access', $lang->t('Access'), $access, array('style'=>'width: 100%;'));
		//$form->getElement('access')->updateAttributes(array('id'=>'sadffhsdfwdasd'));
		// priority
		$form->addElement('select', 'priority', $lang->t('Priority'), $priority, array('style'=>'width: 100%;'));
		//$form->addElement('select', 'priority', $lang->t('Priority'), $priority, array('style'=>'width: 100%;'));

		// events participants
		//employees

		$mls1 = $form->addElement('multiselect', 'empployees', $lang->t('Employees'), $emp, array('size'=>$size, 'style'=>'\'width: 100%\''));
		// customers
		$mls2 = $form->addElement('multiselect', 'contacts', $lang->t('Customers'), $cus, array('size'=>$size, 'style'=>'\'width: 100%\''));

		if($action != 'details') {
			$rb1 = $this->init_module('Utils/RecordBrowser/RecordPicker');
			//ob_start();
			$this->display_module($rb1, array('contact', 'employees', array('CRM_Calendar_Event_Common','decode_contact'), array('Company Name'=>CRM_ContactsCommon::get_main_company())));
			$emp_click = $rb1->create_open_link('Add from table');//ob_get_clean();

			$rb2 = $this->init_module('Utils/RecordBrowser/RecordPicker');
			//ob_start();
			$this->display_module($rb2, array('contact', 'contacts', array('CRM_Calendar_Event_Common','decode_contact'), array('!Company Name'=>CRM_ContactsCommon::get_main_company())));
			$cus_click = $rb2->create_open_link('Add from table');///ob_get_clean();

		} else {
			$emp_click = ''; $cus_click = '';
		}
			//	eval_js( $mls->getElementJs() );
		$form->addElement('text', 'rel_emp', $lang->t('Related Person'), array('style'=>'width: 100%;'));


		// description of event
		$form->addElement('textarea', 'description',  $lang->t('Description'), array('rows'=>6, 'style'=>'width: 100%;'));

		// entry properities
		$form->addElement('static', 'created',  $lang->t('Created'));
		$form->addElement('static', 'edited',  $lang->t('Edited'));

		//buttons
		//$form->addElement('button', 'cancel_button', $lang->ht('Cancel'), 'onClick="parent.location=\''.$this->create_href().'\'"');




		if($action == 'details')
			$form->addElement('submit', 'submit_button', $lang->ht('Edit'));
		else
			$form->addElement('submit', 'submit_button', $lang->ht('Save'));
		$form->addElement('button', 'cancel_button', $lang->ht('Cancel'), $this->create_back_href());
		$form->setDefaults($def);

		switch ($form->getElement('timeless')->getValue()) {
			case '1':
				$timeless = 1;
				break;
			case '0':
			default:
				$timeless = 0;
				break;
		}
		// display form
		if($form->getSubmitValue('submited')) {
			if($action == 'details' && ($event['created_by'] == Acl::get_user() || $event['status'] == 0)) {
				print 'frozen';
				return $this->manage_event($subject, 'edit');
			} else {
				if($form->validate()) {
					if($action == 'new' && $form->process(array(&$this, 'add_event_submit'))) {
						return false;
					} else if($action == 'edit' && $form->process(array(&$this, 'edit_event_submit'))) {
						return false;
					}
				}
			}
		}

		if($action == 'details')
			$form->freeze();

		$theme =  & $this->pack_module('Base/Theme');
		$theme->assign('view_style', 'new_event');
			$theme->assign('repeatable', 0);
			$theme->assign('repeat_forever', 0);
			$theme->assign('edit_mode', 0);
			$theme->assign('timeless', $timeless);
			$theme->assign('emp_click', $emp_click);
			$theme->assign('cus_click', $cus_click);
			$theme->assign('tag', md5($this->get_path().microtime()));
		$form->assign_theme('form', $theme, new HTML_QuickForm_Renderer_TCMSArraySmarty());

		$theme->display();

		Base_ActionBarCommon::add('back',$this->lang->t('Back'), $this->create_back_href());
		if($action == 'details') {
			Base_ActionBarCommon::add('edit',$lang->t('Edit'), $this->create_callback_href(array($this, 'manage_event'), array('edit', array('subject'=>$subject))));
		} else {
			Base_ActionBarCommon::add('save','Save',' href="javascript:void(0)" onClick="'.addcslashes($form->get_submit_form_js(true),'"').'"');
		}

		return true;
	}

	public function edit_event($subject) {
		return $this->manage_event('edit', array('subject'=>$subject));
	}
	public function details_event($subject) {
		return $this->manage_event('details', array('subject'=>$subject));
	}
	///////////////////////////////////////////////////////////////////////////////
	public function delete_event($subject) {
		DB::Execute("delete from calendar_events where id=", array($subject));
	}

	// DETAILS /////////////////////


	public function body(array $args = array()) {

		if($this->is_back())
			return false;

		if(!isset($args['action']) || !isset($args['subject']))
			return false;
		$action = $args['action'];
		$subject = $args['subject'];

		switch($action) {
			case 'edit':
				return $this->manage_event('edit', array('subject'=>$subject));
				break;
			case 'details':
				return $this->manage_event('details', array('subject'=>$subject));
				break;
			case 'add':
				if(!isset($args['date'])) {
					$args['date']['year'] = date('Y');
					$args['date']['month'] = date('M');
					$args['date']['day'] = date('d');
				}
				return $this->manage_event('new', array('date'=>$args['date']));
				break;
			default:
				print 'def';
				return false;

		}
	}

// Leftover from old Event_0
public function add_event($date, $time = null) {
	        if($this->is_back())
	            return false;
	        $lang = & $this->pack_module('Base/Lang');
	        $form = & $this->init_module('Libs/QuickForm');
			$event = & $this->init_module('CRM/Calendar/Event');
            //return $event->manage_event('new', array('date'=>$date, 'time'=>$time));


            $event_names = array();
            $event_type = '';
            foreach($event_list as $event) {
                $form->addElement('select', 'event_type', $lang->t('Event type').':', $event_names, 'onChange="'.$form->get_submit_form_js(false).'"');
	            $form->setDefaults(array('event_type'=>$event_type));
	            $event_type = $form->getElement('event_type')->getValue();
	            $event_type = $event_type[0];
	            // TODO:
	            //  o Should not display form with event types, when event was successfully submited.
	            //$form->display();

	            $event = & $this->init_module($event_type);
	            return $event->manage_event('new', array('date'=>$date, 'time'=>$time));
		}

/*
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

*/
}
}
?>
