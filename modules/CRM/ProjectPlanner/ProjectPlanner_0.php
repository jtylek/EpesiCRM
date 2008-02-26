<?php
/**
 *
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-projectplanner
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_ProjectPlanner extends Module {
	private $lang;

	public function construct() {
		$this->lang = $this->init_module('Base/Lang');
	}

	public function body() {
		$tb = $this->init_module('Utils/TabbedBrowser');
		$tb->set_tab('Overview',array($this,'overview'));
		$tb->set_tab('Project',array($this,'project_view'));
		$tb->set_tab('Employee',array($this,'employee_view'));

		if(isset($_REQUEST['crm_projectplanner_project'])) {
			$tb->switch_tab(1);
			$this->set_module_variable('project',intval($_REQUEST['crm_projectplanner_project']));
		}

		$this->display_module($tb);
		$tb->tag();
	}

	public function overview() {
		$projs_tmp = Apps_ProjectsCommon::get_projects(array('status'=>'in_progress'),array('id','project_name'));
		$projs = array();
		foreach($projs_tmp as $v)
			$projs['p'.$v['id']]=$v['project_name'];
		unset($projs_tmp);
		if(empty($projs)) {
			print($this->lang->t('There is no defined projects'));
			return;
		}

		$c = $this->init_module('Utils/Calendar',array('CRM/ProjectPlanner/OverviewEvent',array('default_view'=>'week',
			'first_day_of_week'=>Utils_PopupCalendarCommon::get_first_day_of_week(),
			'views'=>array('Day','Week','Month'),
			'additional_rows'=>$projs,
			'timeline'=>false,
//			'interval'=>Base_User_SettingsCommon::get('CRM_Calendar','interval'),
			'default_date'=>time()
			)));
		$this->display_module($c);
	}

	public function project_view() {
		$form = $this->init_module('Libs/QuickForm',null,'project_chooser');
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
		$ids = array_keys($projs);
		$sel_proj = & $this->get_module_variable('project',$ids[0]);
		if($form->validate()) {
			$sel_proj = $form->exportValue('proj');
		}
		$form->display();
		CRM_ProjectPlanner_ProjectEventCommon::$project = $sel_proj;


		$c = $this->init_module('Utils/Calendar',array('CRM/ProjectPlanner/ProjectEvent',array('default_view'=>'week',
			'first_day_of_week'=>Utils_PopupCalendarCommon::get_first_day_of_week(),
			'views'=>array('Day','Week','Month'),
			'start_day'=>Variable::get('CRM_ProjectsPlanner__start_day'),
			'end_day'=>Variable::get('CRM_ProjectsPlanner__end_day'),
			'additional_rows'=>array('allday'=>'All day'),
//			'interval'=>Base_User_SettingsCommon::get('CRM_Calendar','interval'),
			'default_date'=>time()
			)));
		$this->display_module($c);
	}

	public function employee_view() {
		$form = $this->init_module('Libs/QuickForm',null,'employee_chooser');
		$emp = array();
		$ret = CRM_ContactsCommon::get_contacts(array('company_name'=>array(CRM_ContactsCommon::get_main_company())));
		foreach($ret as $c_id=>$data)
			$emp[$c_id] = $data['last_name'].' '.$data['first_name'];
		if(empty($emp)) {
			print($this->lang->t('There is no defined employees'));
			return;
		}
		$form->addElement('select','emp',$this->lang->t('Employee'),$emp);
		$ids = array_keys($emp);
		$sel_emp = & $this->get_module_variable('employee',$ids[0]);
		if($form->validate()) {
			$sel_emp = $form->exportValue('emp');
		}
		$form->display();
		CRM_ProjectPlanner_EmployeeEventCommon::$employee = $sel_emp;


		$c = $this->init_module('Utils/Calendar',array('CRM/ProjectPlanner/EmployeeEvent',array('default_view'=>'week',
			'first_day_of_week'=>Utils_PopupCalendarCommon::get_first_day_of_week(),
			'views'=>array('Day','Week','Month'),
			'start_day'=>Variable::get('CRM_ProjectsPlanner__start_day'),
			'end_day'=>Variable::get('CRM_ProjectsPlanner__end_day'),
			'additional_rows'=>array('allday'=>'All day'),
//			'interval'=>Base_User_SettingsCommon::get('CRM_Calendar','interval'),
			'default_date'=>time()
			)));
		$this->display_module($c);
	}

	public static function caption() {
		return "Project planner";
	}

	public function admin() {
		if($this->is_back()) {
			$this->parent->reset();
			return;
		}

		$f = & $this->init_module('Libs/QuickForm');

		$f->addElement('header', 'module_header', $this->lang->t('Work hours'));

		$start_day = array();
		foreach(range(0, 23) as $x)
				$start_day[$x.':00'] = Base_RegionalSettingsCommon::time2reg($x.':00',2,false);
		$end_day = $start_day;

		$f->addElement('select', 'start_day', $this->lang->t('Start day at'), $start_day);
		$f->addElement('select', 'end_day', $this->lang->t('End day at'), $end_day);

		$f->setDefaults(array('start_day'=>Variable::get('CRM_ProjectsPlanner__start_day'),'end_day'=>Variable::get('CRM_ProjectsPlanner__end_day')));

		if($f->validate()) {
			$r = $f->exportValues();
			Variable::set('CRM_ProjectsPlanner__start_day',$r['start_day']);
			Variable::set('CRM_ProjectsPlanner__end_day',$r['end_day']);
			$this->parent->reset();
		} else {
			$f->display();
			Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
			Base_ActionBarCommon::add('save', 'Save', $f->get_submit_form_href());
		}
	}

}

?>
