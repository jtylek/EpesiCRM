<?php
/**
 *
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package custom-projects-planner
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Custom_Projects_Planner extends Module {
	private $lang;

	public function construct() {
		$this->lang = $this->init_module('Base/Lang');
	}

	public function body() {
		$tb = $this->init_module('Utils/TabbedBrowser');
		$tb->set_tab('Overview',array($this,'overview'));
		$tb->set_tab('Project',array($this,'project_view'));
		$tb->set_tab('Employee',array($this,'employee_view'));

		if(isset($_REQUEST['custom_projects_planner_project'])) {
			$tb->switch_tab(1);
			$this->set_module_variable('project',intval($_REQUEST['custom_projects_planner_project']));
		}

		$this->display_module($tb);
		$tb->tag();

		if(!isset($_SESSION['client']['custom_projects_planner_drag_action']))
			$_SESSION['client']['custom_projects_planner_drag_action']='move';
		if($this->isset_unique_href_variable('drag_action'))
			$_SESSION['client']['custom_projects_planner_drag_action']=$this->get_unique_href_variable('drag_action');
		switch($_SESSION['client']['custom_projects_planner_drag_action']) {
			case 'copy':
				Base_ActionBarCommon::add('favorites','Copy one on drag',$this->create_unique_href(array('drag_action'=>'copyX')));
				break;
			case 'copyX':
				Base_ActionBarCommon::add('favorites','Copy multiple on drag',$this->create_unique_href(array('drag_action'=>'move')));
				break;
			case 'move':
			default:
				Base_ActionBarCommon::add('favorites','Move on drag',$this->create_unique_href(array('drag_action'=>'copy')));
				break;
		}	
	}

	public function overview() {
		$projs_tmp = Custom_ProjectsCommon::get_projects(array('status'=>'in_progress'),array('id','project_name'));
		$projs = array('unassigned'=>$this->lang->t('Unassigned employees'));
		foreach($projs_tmp as $v)
			$projs['p'.$v['id']]=$v['project_name'];
		unset($projs_tmp);
		if(empty($projs)) {
			print($this->lang->t('There is no defined projects'));
			return;
		}

		$c = $this->init_module('Utils/Calendar',array('Custom/Projects/Planner/OverviewEvent',array('default_view'=>'week',
			'first_day_of_week'=>Utils_PopupCalendarCommon::get_first_day_of_week(),
			'views'=>array('Day','Week','Month'),
			'custom_rows'=>$projs,
			'timeline'=>false,
			'default_date'=>time()
			)));
		$this->display_module($c);
	}

	public function project_view() {
		$form = $this->init_module('Libs/QuickForm',null,'project_chooser');
		$projs_tmp = Custom_ProjectsCommon::get_projects(array('status'=>'in_progress'),array('id','project_name'));
		$projs = array();
		foreach($projs_tmp as $v)
			$projs[$v['id']]=$v['project_name'];
		unset($projs_tmp);
		if(empty($projs)) {
			print($this->lang->t('There is no defined projects'));
			return;
		}
		$form->addElement('select','proj',$this->lang->t('Project'),$projs,array('onChange'=>$form->get_submit_form_js()));
		$ids = array_keys($projs);
		$sel_proj = & $this->get_module_variable('project',$ids[0]);
		$form->setDefaults(array('proj'=>$sel_proj));
		if($form->validate()) {
			$sel_proj = $form->exportValue('proj');
		}
		$form->display();
		Custom_Projects_Planner_ProjectEventCommon::$project = $sel_proj;


		$c = $this->init_module('Utils/Calendar',array('Custom/Projects/Planner/ProjectEvent',array('default_view'=>'week',
			'first_day_of_week'=>Utils_PopupCalendarCommon::get_first_day_of_week(),
			'views'=>array('Day','Week','Month'),
			'timeline'=>false,
			'default_date'=>time()
			)));

		$date = $c->get_week_start_date();
		
		$pids = DB::GetCol('SELECT employee_id FROM custom_projects_planner_work WHERE start>=%T AND start<%T AND project_id=%d',array($date,$date+86400*7,$sel_proj));
		
		$emps_tmp = CRM_ContactsCommon::get_contacts(array('id'=>$pids),array('id','last_name','first_name'));
		$emps = array('add'=>$this->lang->t('Add employee'));
		foreach($emps_tmp as $v)
			$emps['e'.$v['id']]=$v['last_name'].' '.$v['first_name'];
		unset($emps_tmp);
		if(empty($emps)) {
			print($this->lang->t('There is no defined projects'));
			return;
		}
		$c->settings('custom_rows',$emps);

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
		$form->addElement('select','emp',$this->lang->t('Employee'),$emp,array('onChange'=>$form->get_submit_form_js()));
		$ids = array_keys($emp);
		$sel_emp = & $this->get_module_variable('employee',$ids[0]);
		$form->setDefaults(array('emp'=>$sel_emp));
		if($form->validate()) {
			$sel_emp = $form->exportValue('emp');
		}
		$form->display();
		Custom_Projects_Planner_EmployeeEventCommon::$employee = $sel_emp;

		$c = $this->init_module('Utils/Calendar',array('Custom/Projects/Planner/EmployeeEvent',array('default_view'=>'week',
			'first_day_of_week'=>Utils_PopupCalendarCommon::get_first_day_of_week(),
			'views'=>array('Day','Week','Month'),
			'timeline'=>false,
			'default_date'=>time()
			)));
		
		$date = $c->get_week_start_date();
		
		$pids = DB::GetCol('SELECT project_id FROM custom_projects_planner_work WHERE start>=%T AND start<%T AND employee_id=%d',array($date,$date+86400*7,$sel_emp));
		
		$projs_tmp = Custom_ProjectsCommon::get_projects(array('id'=>$pids),array('id','project_name'));
		$projs = array('add'=>$this->lang->t('Add project'),'vacations'=>$this->lang->t('Vacations'));
		foreach($projs_tmp as $v)
			$projs['p'.$v['id']]=$v['project_name'];
		unset($projs_tmp);
		if(empty($projs)) {
			print($this->lang->t('There is no defined projects'));
			return;
		}
		$c->settings('custom_rows',$projs);
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
				$start_day[$x.':00'] = Base_RegionalSettingsCommon::time2reg($x.':00',2,false,false);
		$end_day = $start_day;

		$f->addElement('select', 'start_day', $this->lang->t('Start day at'), $start_day);
		$f->addElement('select', 'end_day', $this->lang->t('End day at'), $end_day);

		$f->setDefaults(array('start_day'=>Variable::get('Custom_Projects_Planner__start_day'),'end_day'=>Variable::get('Custom_Projects_Planner__end_day')));

		if($f->validate()) {
			$r = $f->exportValues();
			Variable::set('Custom_Projects_Planner__start_day',$r['start_day']);
			Variable::set('Custom_Projects_Planner__end_day',$r['end_day']);
			$this->parent->reset();
		} else {
			$f->display();
			Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
			Base_ActionBarCommon::add('save', 'Save', $f->get_submit_form_href());
		}
	}

}

?>
