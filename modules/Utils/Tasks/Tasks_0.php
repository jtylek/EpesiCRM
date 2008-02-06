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
	private $lang;
	private $priorities;
	private $statuses;
	private $permissions;

	public function construct($id, $allow_add=null,$display_shortterm=null,$display_longterm=null) {
		$this->mid = md5($id);
		$this->real_id = $id;
		$this->allow_add_task = is_bool($allow_add)?$allow_add:true;
		$this->display_shortterm = is_bool($display_shortterm)?$display_shortterm:true;
		$this->display_longterm = is_bool($display_longterm)?$display_longterm:true;
		
		$this->lang = $this->init_module('Base/Lang');
		$this->priorities = array(0 => $this->lang->ht('Low'), 1 => $this->lang->ht('Medium'), 2 => $this->lang->ht('High'));
		$this->statuses = array(0=> $this->lang->ht('Open'), 1 => $this->lang->ht('Closed'));
		$this->permissions = array($this->lang->ht('Public'),$this->lang->ht('Protected'),$this->lang->ht('Private'));

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
			$r->add_data($row['title'],$ass,$rel,$this->statuses[$row['status']],$this->priorities[$row['priority']],Base_RegionalSettingsCommon::time2reg($row['deadline']));
			$r->add_action($this->create_callback_href(array($this,'push_box0'),array('edit',array($row['id']),array($this->real_id,$this->allow_add_task,$this->display_shortterm,$this->display_longterm))),'Edit');
//			$r->add_action($this->create_confirm_callback_href($this->lang->ht('Are you sure?'),array($this,'delete_entry'),$row['id']),'Delete');
		}
		
		$this->display_module($gb);
		
		if($this->allow_add_task)
			Base_ActionBarCommon::add('add','New task',$this->create_callback_href(array($this,'push_box0'),array('edit',null,array($this->real_id,$this->allow_add_task,$this->display_shortterm,$this->display_longterm))));
	}
	
	public function deadline_callback($name, $args, & $def_js) {
		return HTML_QuickForm::createElement('datepicker',$name,$this->lang->t('Deadline date'),array('id'=>'deadline_date'));
	}
	
	public function edit($id=null,$edit=true) {
		if($this->is_back()) {
			$this->pop_box0();
		} else {
			$form = & $this->init_module('Libs/QuickForm',null,'fff');
			$theme =  $this->pack_module('Base/Theme');
			$theme->assign('action',$edit?(isset($id)?'edit':'new'):'view');

			if(isset($id)) {
				$row = DB::GetRow('SELECT * FROM utils_tasks_task WHERE id=%d',array($id));
				$form->setDefaults($row);
			}

			$form->addElement('checkbox','is_deadline',$this->lang->t('Deadline'),false,array('onChange'=>'var x =$(\'deadline_date\');if(this.checked)x.enable();else x.disable();'));
			if(!$form->exportValue('is_deadline'))
				eval_js('$(\'deadline_date\').disable()');
			
			$form->add_table('utils_tasks_task',array(
				array('name'=>'title','label'=>$this->lang->t('Title')),
				array('name'=>'priority','label'=>$this->lang->t('Priority'), 'type'=>'select', 'values'=>$this->priorities),
				array('name'=>'deadline', 'type'=>'callback','func'=>array($this,'deadline_callback')),
				array('name'=>'status','label'=>$this->lang->t('Status'), 'type'=>'select','values'=>$this->statuses),
				array('name'=>'longterm','label'=>$this->lang->t('Longterm')),
				array('name'=>'permission','label'=>$this->lang->t('Permission'), 'type'=>'select','values'=>$this->permissions),
				array('name'=>'description','label'=>$this->lang->t('Description'))
			));

			$emp = array();
			$ret = CRM_ContactsCommon::get_contacts(array('company_name'=>array(CRM_ContactsCommon::get_main_company())));
			foreach($ret as $c_id=>$data)
				$emp[$c_id] = $data['last_name'].' '.$data['first_name'];
			$cus = array();
			$ret = CRM_ContactsCommon::get_contacts(array('!company_name'=>array(CRM_ContactsCommon::get_main_company()), ':Fav'=>true));
			foreach($ret as $c_id=>$data)
				$cus[$c_id] = $data['last_name'].' '.$data['first_name'];
				
			$form->addElement('multiselect', 'emp_id', $this->lang->t('Employees'), $emp);
			$form->addRule('emp_id', $this->lang->t('At least one employee must be assigned to an event.'), 'required');

			$form->addElement('multiselect', 'cus_id', $this->lang->t('Customers'), $cus);
			if($edit) {
				$rb2 = $this->init_module('Utils/RecordBrowser/RecordPicker');
				$this->display_module($rb2, array('contact', 'cus_id', array('CRM_Calendar_EventCommon','decode_contact'), array('!company_name'=>CRM_ContactsCommon::get_main_company()), array('work_phone'=>false, 'mobile_phone'=>false, 'zone'=>false, 'Actions'=>false)));
				$cus_click = $rb2->create_open_link($this->lang->t('Advanced'));
			} else {
				$cus_click = '';
				$form->freeze();
			}
			$theme->assign('cus_click',$cus_click);

			if($form->validate()) {
				$r = $form->exportValues();
				if(isset($id)) {
					if(isset($r['is_deadline']) && $r['is_deadline'])
						DB::Execute('UPDATE utils_tasks_task SET title=%s,description=%s,permission=%d,priority=%d,status=%d,longterm=%b,deadline=%D,edited_by=%d,edited_on=%T WHERE id=%d',array($r['title'],$r['description'],$r['permission'],$r['priority'],$r['status'],isset($r['longterm']) && $r['longterm'],Base_RegionalSettingsCommon::server_date($r['deadline']),Acl::get_user(),time(),$id));
					else
						DB::Execute('UPDATE utils_tasks_task SET title=%s,description=%s,permission=%d,priority=%d,status=%d,longterm=%b,deadline=null,edited_by=%d,edited_on=%T WHERE id=%d',array($r['title'],$r['description'],$r['permission'],$r['priority'],$r['status'],isset($r['longterm']) && $r['longterm'],Acl::get_user(),time(),$id));
				} else {
					if(isset($r['is_deadline']) && $r['is_deadline'])
						DB::Execute('INSERT INTO utils_tasks_task(title,description,permission,priority,status,longterm,deadline,created_by,created_on) VALUES (%s,%s,%d,%d,%d,%b,%D,%d,%T)',array($r['title'],$r['description'],$r['permission'],$r['priority'],$r['status'],isset($r['longterm']) && $r['longterm'],Base_RegionalSettingsCommon::server_date($r['deadline']),Acl::get_user(),time()));
					else
						DB::Execute('INSERT INTO utils_tasks_task(title,description,permission,priority,status,longterm,created_by,created_on) VALUES (%s,%s,%d,%d,%d,%b,%d,%T)',array($r['title'],$r['description'],$r['permission'],$r['priority'],$r['status'],isset($r['longterm']) && $r['longterm'],Acl::get_user(),time()));
					$id = DB::Insert_ID('utils_tasks_task','id');
				}
				$this->pop_box0();
			} else {
				$form->assign_theme('form', $theme);
				$theme->display();

				Base_ActionBarCommon::add('save','Save',$form->get_submit_form_href());
				Base_ActionBarCommon::add('back','Back',$this->create_back_href());
			}
		}
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