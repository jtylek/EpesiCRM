<?php
/**
 * Projects Manager - Change Orders
 * @author jtylek@telaxus.com
 * @copyright jtylek@telaxus.com
 * @license SPL
 * @version 0.1
 * @package apps-projects
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_Projects_ChangeOrders extends Module {

	public function body() {
		location(array('box_main_module'=>'Utils_RecordBrowser', 'box_main_constructor_arguments'=>array('changeorders')));
	}

public function new_changeorder($changeorder){
		Apps_ProjectsCommon::$paste_or_new = $project;
		$rb = $this->init_module('Utils/RecordBrowser','changeorders','changeorders');
		$this->rb = $rb;
		$ret = $rb->view_entry('add', null, array('project_name'=>array($project)));
		$this->set_module_variable('view_or_add', 'add');
		if ($ret==false) {
			$x = ModuleManager::get_instance('/Base_Box|0');
			if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
			return $x->pop_main();
		}
}

/*
public function project_attachment_addon($arg){
		$a = $this->init_module('Utils/Attachment',array($arg['id'],'Apps/Projects/'.$arg['id']));
		$a->additional_header('Project: '.$arg['project_name']); // Field is 'Project Name' but it is converted to lowercase and spec replcaed with '_'
		$a->allow_view_deleted($this->acl_check('view deleted notes'));
		$a->allow_protected($this->acl_check('view protected notes'),$this->acl_check('edit protected notes'));
		$a->allow_public($this->acl_check('view public notes'),$this->acl_check('edit public notes'));
		$this->display_module($a);
	}


public function project_changeorders_addon($arg){
		$a = $this->init_module('Apps/Projects/ChangeOrders',array($arg['id'],'Apps/Projects/'.$arg['id']));
		// $changeorder = array(array('project_name'=>$arg['id']), array('project_name'=>false), array('Fav'=>'DESC'), true);
		$changeorder = array(array('project_name'=>$arg['id']));
		$this->display_module($a);
	}
*/

public function caption(){
		if (isset($this->rb)) return $this->rb->caption();
	}
}

?>