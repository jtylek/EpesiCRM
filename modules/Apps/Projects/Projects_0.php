<?php
/**
 * Projects Manager
 * @author jtylek@telaxus.com
 * @copyright jtylek@telaxus.com
 * @license SPL
 * @version 0.1
 * @package apps-projects
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_Projects extends Module {

	public function body() {
		location(array('box_main_module'=>'Utils_RecordBrowser', 'box_main_constructor_arguments'=>array('projects')));
	}

public function admin() {
		$tb = $this->init_module('Utils/TabbedBrowser');
		$tb->set_tab('Projects', array($this, 'projects_admin'));
		$this->display_module($tb);
		$tb->tag();
	}

public function new_project($project){
		Apps_ProjectsCommon::$paste_or_new = $project;
		$rb = $this->init_module('Utils/RecordBrowser','projects','projects');
		$this->rb = $rb;
		$ret = $rb->view_entry('add', null, array('project_name'=>array($project)));
		$this->set_module_variable('view_or_add', 'add');
		if ($ret==false) {
			$x = ModuleManager::get_instance('/Base_Box|0');
			if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
			return $x->pop_main();
		}
}

public function project_attachment_addon($arg){
		$a = $this->init_module('Utils/Attachment',array($arg['id'],'Apps/Projects/'.$arg['id']));
		$a->additional_header('Project: '.$arg['Project Name']);
		$a->allow_view_deleted($this->acl_check('view deleted notes'));
		$a->allow_protected($this->acl_check('view protected notes'),$this->acl_check('edit protected notes'));
		$a->allow_public($this->acl_check('view public notes'),$this->acl_check('edit public notes'));
		$this->display_module($a);
	}

public function company_projects_addon($arg){
		$rb = $this->init_module('Utils/RecordBrowser','projects');
		$proj = array(array('company_name'=>$arg['id']), array('company_name'=>false), array('Fav'=>'DESC'), true);
		$this->display_module($rb,$proj,'show_data');
	}

public function caption(){
		if (isset($this->rb)) return $this->rb->caption();
	}
}

?>