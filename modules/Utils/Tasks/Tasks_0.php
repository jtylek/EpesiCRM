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
	private $display_closed;
	private $mid;
	private $real_id;
	private $lang;
	private $priorities;
	private $statuses;
	private $permissions;
	private $user_filter;

	public function construct($id, $allow_add=null,$display_shortterm=null,$display_longterm=null,$display_closed=null,$user_filter=null) {
		$this->mid = md5($id);
		$this->real_id = $id;
		$this->allow_add_task = is_bool($allow_add)?$allow_add:true;
		$this->display_shortterm = is_bool($display_shortterm)?$display_shortterm:true;
		$this->display_longterm = is_bool($display_longterm)?$display_longterm:true;
		$this->display_closed = is_bool($display_closed)?$display_closed:false;
		$me = CRM_ContactsCommon::get_contact_by_user_id(Acl::get_user());
		$this->user_filter = is_string($user_filter)?$user_filter:null;

		$this->lang = $this->init_module('Base/Lang');
		$this->priorities = array(0 => $this->lang->ht('Low'), 1 => $this->lang->ht('Medium'), 2 => $this->lang->ht('High'));
		$this->statuses = array(0=> $this->lang->ht('Open'), 1 => $this->lang->ht('Closed'));
		$this->permissions = array($this->lang->ht('Public'),$this->lang->ht('Protected'),$this->lang->ht('Private'));

	}

	public function body() {
		if(!Acl::is_user()) {
			print($this->lang->t('Please log in'));
			return;
		}
		$this->caption = '';
		$gb = & $this->init_module('Utils/GenericBrowser',null,'tasks');
		$gb->set_table_columns(array(
			array('name'=>$this->lang->t('Title'), 'order'=>'title', 'width'=>30),
			array('name'=>$this->lang->t('Assigned to'), 'width'=>15),
			array('name'=>$this->lang->t('Related with'), 'width'=>15),
			array('name'=>$this->lang->t('Status'), 'order'=>'status', 'width'=>10),
			array('name'=>$this->lang->t('Priority'), 'order'=>'priority', 'width'=>10),
			array('name'=>$this->lang->t('Longterm'), 'order'=>'longterm', 'width'=>5, 'enabled'=>($this->display_longterm && $this->display_shortterm)),
			array('name'=>$this->lang->t('Deadline'), 'order'=>'deadline', 'width'=>10),
				));
		if($this->display_longterm && $this->display_shortterm)
			$term = '';
		elseif($this->display_longterm)
			$term = ' AND t.longterm=1';
		elseif($this->display_shortterm)
			$term = ' AND t.longterm=0';
		else
			$term = ' AND 1=0';
		if($this->user_filter) {
			if($this->user_filter!='()')
				$userf = ' AND (SELECT uta.task_id FROM utils_tasks_assigned_contacts uta WHERE uta.task_id=t.id AND uta.contact_id IN '.$this->user_filter.' LIMIT 1) is not null';
			else
				$userf = ' AND 1=0';
		} else
			$userf = '';
		$query = 'SELECT t.* FROM utils_tasks_task t WHERE '.($this->display_closed?'':'t.status=0 AND').' t.page_id=\''.$this->mid.'\' AND (t.permission<2 OR t.created_by='.Acl::get_user().')'.$term.$userf;
		$query_limit = 'SELECT count(t.id) FROM utils_tasks_task t WHERE '.($this->display_closed?'':'t.status=0 AND').' t.page_id=\''.$this->mid.'\' AND (t.permission<2 OR t.created_by='.Acl::get_user().')'.$term.$userf;
		$ret = $gb->query_order_limit($query,$query_limit);
		while($row = $ret->FetchRow()) {
			$r = & $gb->get_new_row();
			$ass='';
			$viewed = DB::GetAssoc('SELECT contact_id,viewed FROM utils_tasks_assigned_contacts WHERE task_id=%d',array($row['id']));
			$ass_arr = CRM_ContactsCommon::get_contacts(array('id'=>array_keys($viewed)));
			foreach($ass_arr as $c_id=>$v) {
				$ass .= '<img src="'.Base_ThemeCommon::get_template_file('images/'.($viewed[$c_id]?'active_on':'active_off').'.png').'">&nbsp;&nbsp;';
				$ass .= CRM_ContactsCommon::contact_format_no_company($v).'<br>';
			}
			$rel = '';
			$related = DB::GetCol('SELECT contact_id FROM utils_tasks_related_contacts WHERE task_id=%d',array($row['id']));
			$rel_arr = CRM_ContactsCommon::get_contacts(array('id'=>$related));

			foreach($related as $v) {
				$rel .= CRM_ContactsCommon::contact_format_default(CRM_ContactsCommon::get_contact($v)).'<br>';
			}

			$stat = $this->statuses[$row['status']];
			if($row['status']==0)
				$stat = '<a '.Utils_TooltipCommon::open_tag_attrs($this->lang->t('Click here to close this task')).' '.$this->create_callback_href(array($this,'close_task'),array($row['id'])).'>'.$stat.'</a>';
			$r->add_data('<a '.($row['description']!==''?Utils_TooltipCommon::open_tag_attrs($row['description']):'').' '.$this->create_callback_href(array($this,'push_box0'),array('edit',array($row['id'],false),array($this->real_id,$this->allow_add_task,$this->display_shortterm,$this->display_longterm,$this->display_closed))).'>'.$row['title'].'</a>',
					$ass,$rel,$stat,
					$this->priorities[$row['priority']],
					'<img src="'.Base_ThemeCommon::get_template_file('images/'.($row['longterm']?'checkbox_on':'checkbox_off').'.png').'">',
					$row['deadline']?Base_RegionalSettingsCommon::time2reg($row['deadline'],false):'--');
			if($row['permission']==0 || $row['created_by']==Acl::get_user())
				$r->add_action($this->create_callback_href(array($this,'push_box0'),array('edit',array($row['id']),array($this->real_id,$this->allow_add_task,$this->display_shortterm,$this->display_longterm,$this->display_closed))),'Edit');
			$r->add_action($this->create_callback_href(array($this,'push_box0'),array('edit',array($row['id'],false),array($this->real_id,$this->allow_add_task,$this->display_shortterm,$this->display_longterm,$this->display_closed))),'View');
			$r->add_action($this->create_confirm_callback_href($this->lang->ht('Are you sure?'),array($this,'delete_task'),$row['id']),'Delete');
			$info = $this->lang->t('Created on: %s',array(Base_RegionalSettingsCommon::time2reg($row['created_on']))).'<br>'.
				$this->lang->t('Created by: %s',array(Base_UserCommon::get_user_login($row['created_by']))).'<br>'.
				($row['edited_by']?$this->lang->t('Edited on: %s',array(Base_RegionalSettingsCommon::time2reg($row['edited_on']))).'<br>'.
				$this->lang->t('Edited by: %s',array(Base_UserCommon::get_user_login($row['edited_by']))).'<br>':'');
			$r->add_info($info);
		}

		$this->display_module($gb);

		if($this->allow_add_task)
			Base_ActionBarCommon::add('add','New task',$this->create_callback_href(array($this,'push_box0'),array('edit',null,array($this->real_id,$this->allow_add_task,$this->display_shortterm,$this->display_longterm,$this->display_closed))));
	}

	public function applet() {
		if(!Acl::is_user()) {
			print($this->lang->t('Please log in'));
			return;
		}
		$gb = & $this->init_module('Utils/GenericBrowser',null,'applet_tasks'.$this->mid);
		$gb->set_table_columns(array(
			array('name'=>$this->lang->t('Title'), 'order'=>'title', 'width'=>80),
			array('name'=>$this->lang->t('Status'), 'order'=>'status', 'width'=>20)
				));
		if($this->display_longterm && $this->display_shortterm)
			$term = '';
		elseif($this->display_longterm)
			$term = ' AND t.longterm=1';
		elseif($this->display_shortterm)
			$term = ' AND t.longterm=0';
		else
			$term = ' AND 1=0';
		if($this->user_filter) {
			if($this->user_filter!='()')
				$userf = ' AND (SELECT uta.task_id FROM utils_tasks_assigned_contacts uta WHERE uta.task_id=t.id AND uta.contact_id IN '.$this->user_filter.' LIMIT 1) is not null';
			else
				$userf = ' AND 1=0';
		} else
			$userf = '';
		$query = 'SELECT t.* FROM utils_tasks_task t WHERE '.($this->display_closed?'':'t.status=0 AND').' t.page_id=\''.$this->mid.'\' AND (t.permission<2 OR t.created_by='.Acl::get_user().')'.$term.$userf;
		$ret = DB::Execute($query);
		while($row = $ret->FetchRow()) {
			$r = & $gb->get_new_row();
			$ass='';
			$viewed = DB::GetAssoc('SELECT contact_id,viewed FROM utils_tasks_assigned_contacts WHERE task_id=%d',array($row['id']));
			$ass_arr = CRM_ContactsCommon::get_contacts(array('id'=>array_keys($viewed)));
			foreach($ass_arr as $c_id=>$v)
				$ass .= '<img src="'.Base_ThemeCommon::get_template_file('images/'.($viewed[$c_id]?'active_on':'active_off').'.png').'">&nbsp;&nbsp;' . $v['first_name'].' '.$v['last_name'] . '<br>';

			$rel = '';
			$related = DB::GetCol('SELECT contact_id FROM utils_tasks_related_contacts WHERE task_id=%d',array($row['id']));
			$rel_arr = CRM_ContactsCommon::get_contacts(array('id'=>$related));
			foreach($rel_arr as $c_id=>$v) {
				$rel .= $v['first_name'].' '.$v['last_name'].'<br>';
			}
			$stat = $this->statuses[$row['status']];
			if($row['status']==0)
				$stat = '<a '.Utils_TooltipCommon::open_tag_attrs($this->lang->t('Click here to close this task')).' '.$this->create_callback_href(array($this,'close_task'),array($row['id'])).'>'.$stat.'</a>';
			$r->add_data('<a '.($row['description']!==''?Utils_TooltipCommon::open_tag_attrs($row['description']):'').' '.$this->create_callback_href(array($this,'push_box0'),array('edit',array($row['id'],false),array($this->real_id,$this->allow_add_task,$this->display_shortterm,$this->display_longterm,$this->display_closed))).'>'.$row['title'].'</a>',$stat);
			$r->add_action($this->create_callback_href(array($this,'push_box0'),array('edit',array($row['id'],false),array($this->real_id,$this->allow_add_task,$this->display_shortterm,$this->display_longterm,$this->display_closed))),'View');
			$info = $this->lang->t('Priority: %s',array($this->priorities[$row['priority']])).'<br>'.
				$this->lang->t('Assigned to: %s',array($ass)).'<br>'.
				$this->lang->t('Related with: %s',array($rel)).'<br>'.
				$this->lang->t('Longterm: %s',array($row['longterm']?'yes':'no')).'<br>'.
				$this->lang->t('Deadline: %s',array($row['deadline']?Base_RegionalSettingsCommon::time2reg($row['deadline'],false):'--')).'<hr>'.
				$this->lang->t('Created on: %s',array(Base_RegionalSettingsCommon::time2reg($row['created_on']))).'<br>'.
				$this->lang->t('Created by: %s',array(Base_UserCommon::get_user_login($row['created_by']))).'<br>'.
				($row['edited_by']?$this->lang->t('Edited on: %s',array(Base_RegionalSettingsCommon::time2reg($row['edited_on']))).'<br>'.
				$this->lang->t('Edited by: %s',array(Base_UserCommon::get_user_login($row['edited_by']))).'<br>':'');
			$r->add_info($info);

		}

		$this->display_module($gb);
	}

	public function delete_task($id) {
		Utils_AttachmentCommon::persistent_mass_delete('Task:'.$id);
		DB::Execute('DELETE FROM utils_tasks_assigned_contacts WHERE task_id=%d',array($id));
		DB::Execute('DELETE FROM utils_tasks_related_contacts WHERE task_id=%d',array($id));
		DB::Execute('DELETE FROM utils_tasks_task WHERE id=%d',array($id));
	}

	public function delete_task_pop($id) {
		$this->delete_task($id);
		$this->pop_box0();
	}

	public function close_task($id) {
		DB::Execute('UPDATE utils_tasks_task SET status=1 WHERE id=%d',array($id));
	}

	public function deadline_callback($name, $args, & $def_js) {
		return HTML_QuickForm::createElement('datepicker',$name,$this->lang->t('Deadline date'),array('id'=>'deadline_date'));
	}

	public function edit($id=null,$edit=true) {
		$this->caption = ': '.$this->lang->t($id===null?'new':($edit?'edit':'view'));
		$me = CRM_ContactsCommon::get_contact_by_user_id(Acl::get_user());
		if($me!==null && isset($id))
			DB::Execute('UPDATE utils_tasks_assigned_contacts SET viewed=1 WHERE contact_id=%d AND task_id=%d',array($me['id'],$id));
		if($this->is_back()) {
			$this->pop_box0();
		} else {
			$form = & $this->init_module('Libs/QuickForm',null,'fff');
			$theme =  $this->pack_module('Base/Theme');
			$theme->assign('action',$edit?(isset($id)?'edit':'new'):'view');


			$form->addElement('checkbox','is_deadline',$this->lang->t('Deadline'),false,array('onChange'=>'var x =$(\'deadline_date\');if(this.checked)x.enable();else x.disable();'));
			$form->add_table('utils_tasks_task',array(
				array('name'=>'title','label'=>$this->lang->t('Title'),'param'=>array('id'=>'task_title')),
				array('name'=>'priority','label'=>$this->lang->t('Priority'), 'type'=>'select', 'values'=>$this->priorities),
				array('name'=>'deadline', 'type'=>'callback','func'=>array($this,'deadline_callback')),
				array('name'=>'status','label'=>$this->lang->t('Status'), 'type'=>($edit?'select':'static'),'values'=>($edit?$this->statuses:'')),
				array('name'=>'longterm','label'=>$this->lang->t('Longterm')),
				array('name'=>'permission','label'=>$this->lang->t('Permission'), 'type'=>'select','values'=>$this->permissions),
				array('name'=>'description','label'=>$this->lang->t('Description'))
			));

			if(isset($id)) {
				$defaults = DB::GetRow('SELECT * FROM utils_tasks_task WHERE id=%d',array($id));
				$related = DB::GetCol('SELECT contact_id FROM utils_tasks_related_contacts WHERE task_id=%d',array($id));
				$assigned = DB::GetAssoc('SELECT contact_id,viewed FROM utils_tasks_assigned_contacts WHERE task_id=%d',array($id));
				$my_task = $defaults['created_by']==Acl::get_user();
				if($defaults['permission']==2 && !$my_task) {
					print($this->lang->t('You are not allowed to view this task'));
					return;
				}
				if(!$edit) {
					if($defaults['status']==1/* || $me===null || !isset($assigned[$me['id']])*/)
						$defaults['status'] = $this->statuses[$defaults['status']];
					else
						$defaults['status'] = '<a '.Utils_TooltipCommon::open_tag_attrs($this->lang->t('Click here to close this task')).' '.$this->create_callback_href(array($this,'close_task'),array($id)).'>'.$this->statuses[$defaults['status']].'</a>';
					$defaults['created_by'] = Base_UserCommon::get_user_login($defaults['created_by']);
					$defaults['created_on'] = Base_RegionalSettingsCommon::time2reg($defaults['created_on']);
					$defaults['edited_by'] = $defaults['edited_by']?Base_UserCommon::get_user_login($defaults['edited_by']):'--';
					$defaults['edited_on'] = $defaults['edited_on']?Base_RegionalSettingsCommon::time2reg($defaults['edited_on']):'--';
				} else {
					$defaults['is_deadline'] = ($defaults['deadline']==true);
					if($defaults['permission']==1 && !$my_task) {
						print($this->lang->t('You are not allowed to edit this task'));
						return;
					}
				}
				$form->setDefaults($defaults);
				if ($edit) $form->setDefaults(array('cus_id'=>$related,'emp_id'=>array_keys($assigned)));
			} elseif($edit) { //new task
				if($me!==null)
					$form->setDefaults(array('emp_id'=>array($me['id'])));
				$related = array();
				$assigned = array();
			}

			if(!$form->exportValue('is_deadline'))
				eval_js('$(\'deadline_date\').disable()');

			if($edit) {
				$emp = array();
				$ret = CRM_ContactsCommon::get_contacts(array('company_name'=>array(CRM_ContactsCommon::get_main_company())));
				foreach($ret as $c_id=>$data) {
					$emp[$c_id] = $data['last_name'].' '.$data['first_name'];
					if(!$edit)
						$emp[$c_id] = '<img src="'.Base_ThemeCommon::get_template_file('images/'.((isset($assigned[$c_id]) && $assigned[$c_id])?'active_on':'active_off').'.png').'">&nbsp;&nbsp;' . $emp[$c_id];
				}
				$cus = array();
				$ret = CRM_ContactsCommon::get_contacts(array('!company_name'=>array(CRM_ContactsCommon::get_main_company()), '(:Fav'=>true, '|:Recent'=>true, '|id'=>$related));
				foreach($ret as $c_id=>$data)
					$cus[$c_id] = CRM_ContactsCommon::contact_format_default($data);
	
				$form->addElement('multiselect', 'emp_id', $this->lang->t('Employees'), $emp);
				$form->addRule('emp_id', $this->lang->t('At least one employee must be assigned to an event.'), 'required');
	
				$form->addElement('multiselect', 'cus_id', $this->lang->t('Customers'), $cus);
				
				$rb2 = $this->init_module('Utils/RecordBrowser/RecordPicker');
				$this->display_module($rb2, array('contact', 'cus_id', array('CRM_Calendar_EventCommon','decode_contact'), array('!company_name'=>CRM_ContactsCommon::get_main_company()), array('work_phone'=>false, 'mobile_phone'=>false, 'zone'=>false, 'Actions'=>false), array('last_name'=>'ASC')));
				$cus_click = $rb2->create_open_link($this->lang->t('Advanced'));
			} else {
				$form->addElement('static', 'emp_id', $this->lang->t('Employees'));
				$form->addElement('static', 'cus_id', $this->lang->t('Customers'));
				$cus_id = '';
				$emp_id = '';
				foreach ($related as $v)
					$cus_id .= CRM_ContactsCommon::contact_format_default(CRM_ContactsCommon::get_contact($v)).'<br>';
				foreach (array_keys($assigned) as $v) {
					$emp_id .= '<img src="'.Base_ThemeCommon::get_template_file('images/'.((isset($assigned[$v]) && $assigned[$v])?'active_on':'active_off').'.png').'">&nbsp;&nbsp;';
					$emp_id .= CRM_ContactsCommon::contact_format_no_company(CRM_ContactsCommon::get_contact($v)).'<br>';
				}
				$form->setDefaults(array('cus_id'=>$cus_id,'emp_id'=>$emp_id));
				$cus_click = '';
				$form->freeze();
			}
			$theme->assign('cus_click',$cus_click);
			if(!$edit) {
				$form->addElement('static', 'created_by',  $this->lang->t('Created by'));
				$form->addElement('static', 'created_on',  $this->lang->t('Created on'));
				$form->addElement('static', 'edited_by',  $this->lang->t('Edited by'));
				$form->addElement('static', 'edited_on',  $this->lang->t('Edited on'));
			}

			if($edit && isset($id)) {
				$form->addElement('checkbox', 'notify', $this->lang->t('Notify assigned users'));
			}

			$theme->assign('permission_id',$form->exportValue('permission'));
			$theme->assign('priority_id',$form->exportValue('priority'));
			$theme->assign('status_id',$form->exportValue('status'));

			if($form->validate()) {
				$r = $form->exportValues();
				if(isset($id)) {
					if(isset($r['is_deadline']) && $r['is_deadline'])
						DB::Execute('UPDATE utils_tasks_task SET title=%s,description=%s,permission=%d,priority=%d,status=%d,longterm=%b,deadline=%D,edited_by=%d,edited_on=%T WHERE id=%d',array($r['title'],$r['description'],$r['permission'],$r['priority'],$r['status'],isset($r['longterm']) && $r['longterm'],$r['deadline'],Acl::get_user(),time(),$id));
					else
						DB::Execute('UPDATE utils_tasks_task SET title=%s,description=%s,permission=%d,priority=%d,status=%d,longterm=%b,deadline=null,edited_by=%d,edited_on=%T WHERE id=%d',array($r['title'],$r['description'],$r['permission'],$r['priority'],$r['status'],isset($r['longterm']) && $r['longterm'],Acl::get_user(),time(),$id));
				} else {
					if(isset($r['is_deadline']) && $r['is_deadline'])
						DB::Execute('INSERT INTO utils_tasks_task(title,description,permission,priority,status,longterm,deadline,created_by,created_on,page_id) VALUES (%s,%s,%d,%d,%d,%b,%D,%d,%T,%s)',array($r['title'],$r['description'],$r['permission'],$r['priority'],$r['status'],isset($r['longterm']) && $r['longterm'],$r['deadline'],Acl::get_user(),time(),$this->mid));
					else
						DB::Execute('INSERT INTO utils_tasks_task(title,description,permission,priority,status,longterm,created_by,created_on,page_id) VALUES (%s,%s,%d,%d,%d,%b,%d,%T,%s)',array($r['title'],$r['description'],$r['permission'],$r['priority'],$r['status'],isset($r['longterm']) && $r['longterm'],Acl::get_user(),time(),$this->mid));
					$id = DB::Insert_ID('utils_tasks_task','id');
				}
				if(isset($r['notify']) && $r['notify']) {
					$assigned = array();
					DB::Execute('DELETE FROM utils_tasks_assigned_contacts WHERE task_id=%d',array($id));
				}
				foreach($r['emp_id'] as $em) {
					if(isset($assigned[$em]))
						unset($assigned[$em]);
					else
						DB::Execute('INSERT INTO utils_tasks_assigned_contacts(contact_id,task_id) VALUES(%d,%d)',array($em,$id));
				}
				if(isset($assigned))
					foreach($assigned as $k=>$v) {
						DB::Execute('DELETE FROM utils_tasks_assigned_contacts WHERE task_id=%d AND contact_id=%d',array($id,$k));
					}
				DB::Execute('DELETE FROM utils_tasks_related_contacts WHERE task_id=%d',array($id));
				foreach($r['cus_id'] as $cu)
					DB::Execute('INSERT INTO utils_tasks_related_contacts(task_id,contact_id) VALUES(%d,%d)',array($id,$cu));

				if($me!==null && isset($id))
					DB::Execute('UPDATE utils_tasks_assigned_contacts SET viewed=1 WHERE contact_id=%d AND task_id=%d',array($me['id'],$id));

				$this->pop_box0();
			} else {
				$form->assign_theme('form', $theme);

				if($edit) {
					Base_ActionBarCommon::add('save','Save',$form->get_submit_form_href());
					$theme->assign('attachments','');
				} else {
					if($defaults['permission']==0 || $my_task)
						Base_ActionBarCommon::add('edit','Edit',$this->create_callback_href(array($this,'push_box0'),array('edit',array($id),array($this->real_id,$this->allow_add_task,$this->display_shortterm,$this->display_longterm,$this->display_closed))));
					Base_ActionBarCommon::add('delete','Delete',$this->create_confirm_callback_href($this->lang->ht('Are you sure?'),array($this,'delete_task_pop'),$id));
					$a = $this->init_module('Utils/Attachment',array('Task:'.$id,'CRM/Tasks/'.$this->mid));
					$a->additional_header($this->lang->t('Task: %s',array($defaults['title'])));
					$theme->assign('attachments',$this ->get_html_of_module($a));
				}
				Base_ActionBarCommon::add('back','Back',$this->create_back_href());

				$theme->display();
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

	public function caption() {
		return 'Tasks'.$this->caption;
	}
}

?>
