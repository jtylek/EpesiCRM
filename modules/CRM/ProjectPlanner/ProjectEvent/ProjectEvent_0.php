<?php
/**
 * Calendar event module
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-calendar-event
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_ProjectPlanner_ProjectEvent extends Utils_Calendar_Event {
	private $lang;

	public function view($id) {
		if($this->is_back()) $this->back_to_calendar();
		$this->view_event('view', $id);
	}

	public function edit($id) {
		if($this->is_back()) $this->back_to_calendar();
		$this->view_event('edit',$id);
	}

	public function add($def_date,$timeless=false) {
		if($this->is_back()) $this->back_to_calendar();
		$this->view_event('new', $def_date, $timeless);
	}

	public function view_event($action, $id=null, $timeless=false){
		if($this->is_back()) return false;

		$this->lang = $this->pack_module('Base/Lang');
		$form = $this->init_module('Libs/QuickForm');
		$theme =  $this->pack_module('Base/Theme');
		$theme->assign('action',$action);
		if($action=='new') {
			$tt = $id-$id%300;
			$defs = array(
				'date_s' => $id,
				'time_s' => $tt,
				'time_e' => $tt+3600,
				'allday' => $timeless
				);

		} else {
			$x = DB::GetRow('SELECT * FROM crm_projectplanner_work WHERE id=%d',array($id));
			$defs = array(
				'date_s' => $x['start'],
				'time_s' => $x['start'],
				'time_e' => $x['end'],
				'emp' => $x['employee_id']);
		}
		$form->setDefaults($defs);

		$proj_id = $this->get_module_variable('project',CRM_ProjectPlanner_ProjectEventCommon::$project);

		$emp = array();
		$emp_tmp = CRM_ContactsCommon::get_contacts(array('company_name'=>array(CRM_ContactsCommon::get_main_company())));
		foreach($emp_tmp as $c_id=>$data)
			$emp[$c_id] = $data['last_name'].' '.$data['first_name'];
		unset($emp_tmp);
		if(empty($emp)) {
			print($this->lang->t('There is no defined employees'));
			return;
		}
		$form->addElement('select','emp',$this->lang->t('Employee'),$emp);


		if(!isset($proj_id)) {
			$projs_tmp = Apps_ProjectsCommon::get_projects(array('status'=>'in_progress'),array('id','project_name'));
			$projs = array();
			foreach($projs_tmp as $v)
				$projs[$v['id']]=$v['project_name'];
			unset($projs_tmp);
			if(empty($projs)) {
				print($this->lang->t('There is no defined projects'));
				return;
			}
			$form->addElement('select','proj',$this->lang->t('Project'),$projs);
		} else {
			$proj = Apps_ProjectsCommon::get_project($proj_id);
			$form->addElement('static','proj',$this->lang->t('Project'),$proj['project_name']);
		}

		$time_format = Base_RegionalSettingsCommon::time_12h()?'h:i:a':'H:i';

		$form->addElement('datepicker', 'date_s', $this->lang->t('Date'));
		$form->addRule('date_s', $this->lang->t('Field is required!'), 'required');
		$lang_code = Base_LangCommon::get_lang_code();
		$form->addElement('date', 'time_s', $this->lang->t('Start time'), array('format'=>$time_format, 'optionIncrement'  => array('i' => 5),'language'=>$lang_code));
		$form->addElement('date', 'time_e', $this->lang->t('End time'), array('format'=>$time_format, 'optionIncrement'  => array('i' => 5), 'language'=>$lang_code));
		$theme->assign('time_s_id','time_s');
		$theme->assign('time_e_id','time_e');

		if($action=='new') {
			eval_js_once('crm_projectplanner_allday = function(val) {'.
					'var cal_style;'.
					'if(val){'.
					'cal_style = \'none\';'.
					'}else{'.
					'cal_style = \'block\';'.
					'}'.
					'$(\'time_e\').style.display = cal_style;'.
					'$(\'time_s\').style.display = cal_style;'.
				'}');
			$form->addElement('checkbox', 'allday', $this->lang->t('All day'), null,array('onClick'=>'crm_projectplanner_allday(this.checked)'));
			eval_js('crm_projectplanner_allday('.$timeless.')');
		} else
			$form->addElement('static','allday');

		$form->registerRule('check_dates', 'callback', 'check_dates', $this);
		$form->addRule(array('time_e', 'time_s', 'allday'), 'End date must be after begin date...', 'check_dates');

		if($form->validate()) {
			$v = $form->exportValues();
			//trigger_error(print_r($v,true));
			$emp_id = $v['emp'];
			if(!isset($proj_id))
				$proj_id = $v['proj'];
			if(isset($v['allday']) && $v['allday']) {
				$start = strtotime($v['date_s'].' '.Variable::get('CRM_ProjectsPlanner__start_day'));
				$end = strtotime($v['date_s'].' '.Variable::get('CRM_ProjectsPlanner__end_day'));
			} else {
				$date = strtotime($v['date_s']);
				$start = $date+$this->recalculate_time($v['time_s']);
				$end = $date+$this->recalculate_time($v['time_e']);
			}
			if($action=='new') {
				DB::Execute('INSERT INTO crm_projectplanner_work(employee_id,project_id,start,end) VALUES(%d,%d,%T,%T)',array($emp_id,$proj_id,$start,$end));
			} else {
				DB::Execute('UPDATE crm_projectplanner_work SET project_id=%d,start=%T,end=%T WHERE id=%d',array($proj_id,$start,$end,$id));
			}
			$this->back_to_calendar();
			return;
		}
		if($action=='view')
			$form->freeze();

		$form->assign_theme('form', $theme);
		$theme->display();

		if($action == 'view') {
			Base_ActionBarCommon::add('edit',$this->lang->t('Edit'), $this->create_callback_href(array($this, 'view_event'), array('edit', $id)));
		} else {
			Base_ActionBarCommon::add('save','Save',' href="javascript:void(0)" onClick="'.addcslashes($form->get_submit_form_js(true),'"').'"');
		}
		Base_ActionBarCommon::add('back','Back',$this->create_back_href());


		return true;
	}

	public function check_dates($arg) {
		if($arg[2]) return true;
		$start = $this->recalculate_time($arg[1]);
		$end = $this->recalculate_time($arg[0]);
		return $end >= $start;
	}

	private function recalculate_time($time) {
		if (isset($time['a'])) {
			$result = 60*($time['i']+60*($time['h']));
			if ($time['a']=='pm') $result += 43200;
			if ($time['h']==12) {
				if ($time['a']=='pm') $result -= 43200; else $result -= 43200;
			}
		} else $result = 60*($time['i']+60*($time['H']));
		return $result;
	}


}

?>
