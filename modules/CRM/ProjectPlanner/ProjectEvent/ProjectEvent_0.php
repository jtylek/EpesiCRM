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
			$defs = array(
				'time_s' => strtotime(Base_RegionalSettingsCommon::time2reg(Variable::get('CRM_ProjectsPlanner__start_day'),true,true,true,false)),
				'time_e' => strtotime(Base_RegionalSettingsCommon::time2reg(Variable::get('CRM_ProjectsPlanner__end_day'),true,true,true,false)),
				'date_s' => Base_RegionalSettingsCommon::time2reg($id,false),
				'allday' => true
				);
		} else {
			$x = DB::GetRow('SELECT * FROM crm_projectplanner_work WHERE id=%d',array($id));
			$defs = array(
				'emp' => $x['employee_id'],
				'date_s' => Base_RegionalSettingsCommon::time2reg($x['start'],false),
				'allday' => $x['allday']);
			if($x['allday']) {
				if($action=='edit') {
					$defs['time_s'] = strtotime(Base_RegionalSettingsCommon::time2reg(Variable::get('CRM_ProjectsPlanner__start_day'),true,true,true,false));
					$defs['time_e'] = strtotime(Base_RegionalSettingsCommon::time2reg(Variable::get('CRM_ProjectsPlanner__end_day'),true,true,true,false));
				}
			} else {
				$defs['time_s'] = Base_RegionalSettingsCommon::time2reg($x['start'],true,true,true,false);
				$defs['time_e'] = Base_RegionalSettingsCommon::time2reg($x['end'],true,true,true,false);
			}
		}
		$form->setDefaults($defs);

		$proj_id = $this->get_module_variable('project',CRM_ProjectPlanner_ProjectEventCommon::$project);

		$proj = Apps_ProjectsCommon::get_project($proj_id);
		$form->addElement('static','proj',$this->lang->t('Project'),$proj['project_name']);
		
		$emps_tmp = CRM_ContactsCommon::get_contacts(array('company_name'=>array(CRM_ContactsCommon::get_main_company())),array('id','first_name','last_name'));
		$emps = array();
		foreach($emps_tmp as $v)
			$emps[$v['id']] = $v['last_name'].' '.$v['first_name'];
		unset($emps_tmp);
		if(empty($emps)) {
			print($this->lang->t('There is no employees'));
			return;
		}
		$form->addElement('select','emp',$this->lang->t('Employee'),$emps);

		$time_format = Base_RegionalSettingsCommon::time_12h()?'h:i:a':'H:i';

		if($action!='view') {
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
			eval_js('crm_projectplanner_allday('.$defs['allday'].')');
		} else
			$form->addElement('static','allday');

		$form->addElement('static', 'date_s', $this->lang->t('Date'));
		if(!$defs['allday'] || $action!='view') {
			$lang_code = Base_LangCommon::get_lang_code();
			$form->addElement('date', 'time_s', $this->lang->t('Start time'), array('format'=>$time_format, 'optionIncrement'  => array('i' => 5),'language'=>$lang_code));
			$form->addElement('date', 'time_e', $this->lang->t('End time'), array('format'=>$time_format, 'optionIncrement'  => array('i' => 5), 'language'=>$lang_code));
		} else {
			$form->addElement('static', 'time_s');
			$form->addElement('static', 'time_e');
		}
		$theme->assign('time_s_id','time_s');
		$theme->assign('time_e_id','time_e');

		$form->registerRule('check_dates', 'callback', 'check_dates', $this);
		$form->addRule(array('time_e', 'time_s', 'allday'), 'End date must be after begin date...', 'check_dates');

		if($form->validate()) {
			$v = $form->exportValues();
			if($timeless=='add')
				$emp_id = $v['emp'];
			else
				$emp_id = ltrim($timeless,'e');
			$allday = isset($v['allday']) && $v['allday'];
			$time = ($action=='edit')?strtotime($x['start']):$id;
			if($allday) {
				$start = $time;
				$end = $time;
			} else {
				$start = $time+$this->recalculate_time($v['time_s']);
				$end = $time+$this->recalculate_time($v['time_e']);
			}
			print($proj_id);
			if($action=='new') {
				DB::Execute('INSERT INTO crm_projectplanner_work(employee_id,project_id,start,end,allday,vacations) VALUES(%d,%d,%T,%T,%b,0)',array($emp_id,$proj_id,$start,$end,$allday));
			} else {
				DB::Execute('UPDATE crm_projectplanner_work SET start=%T,end=%T,allday=%b WHERE id=%d',array($start,$end,$allday,$id));
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
