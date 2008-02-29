<?php
/**
 * Projects Manager - Equipment
 * @author jtylek@telaxus.com
 * @copyright jtylek@telaxus.com
 * @license SPL
 * @version 0.1
 * @package apps-projects
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_Projects_Equipment extends Module {

	public function body() {
		location(array('box_main_module'=>'Utils_RecordBrowser', 'box_main_constructor_arguments'=>array('equipment')));
	}

public function new_equipment($equipment){
		Apps_ProjectsCommon::$paste_or_new = $project;
		$rb = $this->init_module('Utils/RecordBrowser','equipment','equipment');
		$this->rb = $rb;
		$ret = $rb->view_entry('add', null, array('project_name'=>array($project)));
		$this->set_module_variable('view_or_add', 'add');
		if ($ret==false) {
			$x = ModuleManager::get_instance('/Base_Box|0');
			if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
			return $x->pop_main();
		}
}

public function equipment_attachment_addon($arg){
		$a = $this->init_module('Utils/Attachment',array($arg['id'],'Apps/Projects/Equipment/'.$arg['id']));
		$a->additional_header('Equipment Request: '.$arg['lift_eq_no']);
		$this->display_module($a);
	}

public function project_equipment_addon($arg){
		// always 'Utils/RecordBrowser','table','unique_internal_name' - can be anything
		$rb = $this->init_module('Utils/RecordBrowser','equipment','equipment_addon');
		//$rb->enable_quick_new_records();
		// Base_ActionBarCommon::add('add',Base_LangCommon::ts('CRM_Contacts','Add contact'), $this->create_callback_href(array($this, 'company_addon_new_contact'), array($arg['id'])));
		// $rb->set_button($this->create_callback_href(array($this, 'company_addon_new_contact'), array($arg['id'])));
		// array with arguments: first-criteria, column names to hide (or to force to show), sorting - any column visible (from right to left), true=always
		$this->display_module($rb, array(array('project_name'=>array($arg['id'])), array('company_name'=>false), array('Fav'=>'DESC', 'Lift Eq No'=>'ASC'), true), 'show_data');
	}

public function caption(){
		if (isset($this->rb)) return $this->rb->caption();
	}
}

?>