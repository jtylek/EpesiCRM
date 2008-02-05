<?php
/**
 * Tasks
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package utils-tasks
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Tasks extends Module {
	private $allow_add_task;
	private $display_shortterm;
	private $display_longterm;
	private $mid;
	private $real_id;

	public function construct($id, $allow_add=null,$display_shortterm=null,$display_longterm=null) {
		$this->mid = md5($id);
		$this->real_id = $id;
		$this->allow_add_task = is_bool($allow_add)?$allow_add:true;
		$this->display_shortterm = is_bool($display_shortterm)?$display_shortterm:true;
		$this->display_longterm = is_bool($display_longterm)?$display_longterm:true;
	}

	public function body() {
		$gb = & $this->init_module('Utils/GenericBrowser',null,'tasks');
		$gb->set_table_columns(array(
			array('name'=>$this->lang->t('Title'), 'order'=>'title', 'width'=>30),
			array('name'=>$this->lang->t('Assigned to'), 'width'=>15),
			array('name'=>$this->lang->t('Related with'), 'width'=>15),
			array('name'=>$this->lang->t('Status'), 'order'=>'status', 'width'=>10),
			array('name'=>$this->lang->t('Priority'), 'order'=>'priority', 'width'=>10),
			array('name'=>$this->lang->t('Deadline'), 'order'=>'deadline', 'width'=>15),
				));
		if($this->display_longterm && $this->display_shortterm) 
			$term = '';
		elseif($this->display_longterm)
			$term = ' AND t.longterm=1';
		elseif($this->display_shortterm) 
			$term = ' AND t.longterm=0';
		else 
			$term = ' AND 1=0';
		$query = 'SELECT t.id,t.title,t.status,t.priority,t.deadline FROM utils_tasks_task t WHERE t.status=0 AND t.page_id=\''.$this->mid.'\''.$term;
		$query_limit = 'SELECT count(t.id) FROM utils_tasks_task t WHERE t.status=0 AND t.page_id=\''.$this->mid.'\''.$term;
		$ret = $gb->query_order_limit($query,$query_limit);
		while($row = $ret->FetchRow()) {
			$r = & $gb->get_new_row();
			$ass='';
			$viewed = DB::GetAssoc('SELECT contact_id,viewed FROM utils_tasks_assigned_contacts WHERE task_id=%d',array($row['id']));
			$ass_arr = CRM_ContactsCommon::get_contacts(array('id'=>array_keys($viewed)));
			print_r($ass_arr);
			$rel = '';
			$r->add_data($row['title'],$ass,$rel,$row['status'],$row['priority'],Base_RegionalSettingsCommon::time2reg($row['deadline']));
			$r->add_action($this->create_callback_href(array($this,'push_box0'),array('edit',array($row['id']),array($this->real_id,$this->allow_add_task,$this->display_shortterm,$this->display_longterm))),'Edit');
//			$r->add_action($this->create_confirm_callback_href($this->lang->ht('Are you sure?'),array($this,'delete_entry'),$row['id']),'Delete');
		}
		
		$this->display_module($gb);
		
		if($this->allow_add_task)
			Base_ActionBarCommon::add('add','New task',$this->create_callback_href(array($this,'push_box0'),array('edit',array(false),array($this->real_id,$this->allow_add_task,$this->display_shortterm,$this->display_longterm))));
	}
	
	public function edit($id=null) {
	
	}

	public function pop_box0() {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->pop_main();
	}

	public function push_box0($func,$args,$const_args) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('Utils/Tasks',$func,$args,$const_args);
	}

}

?>