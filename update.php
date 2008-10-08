<?php
/**
 * Epesi core updater.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

include_once('include/misc.php');

// ******************* Database Patch functions *************
function PatchDBAddColumn($table_name,$table_column,$table_column_def){
	// First check if table needs to be altered
	if (!array_key_exists(strtoupper($table_column),DB::MetaColumnNames($table_name)))
		{
		$q = DB::dict()->AddColumnSQL($table_name,$table_column.' '.$table_column_def);
		foreach($q as $qq)
		DB::Execute($qq);
		return true;
		}
		else
		{
		return false;
		}
	} //end of PatchDBAddColumn

function PatchDBDropColumn($table_name,$table_column){
	// First check if table needs to be altered
	if (array_key_exists(strtoupper($table_column),DB::MetaColumnNames($table_name)))
		{
		$q = DB::dict()->DropColumnSQL($table_name,$table_column);
		foreach($q as $qq)
		DB::Execute($qq);
		return true;
		}
		else
		{
		return false;
		}
	} //end of PatchDBDropColumn


function PatchDBRenameColumn($table_name,$old_table_column,$new_table_column,$table_column_def){
	// First check if column exists
	if (array_key_exists(strtoupper($old_table_column),DB::MetaColumnNames($table_name)))
		{
		$q = DB::dict()->RenameColumnSQL($table_name,$old_table_column,$new_table_column,$new_table_column.' '.$table_column_def);
		foreach($q as $qq)
		DB::Execute($qq);
		return true;
		}
		else
		{
		return false;
		}
	} //end of DBRenameColumn

function install_default_theme_common_files($dir,$f) {
	if(class_exists('ZipArchive')) {
		$zip = new ZipArchive;
		if ($zip->open($dir.$f.'.zip') == 1)
			$zip->extractTo(DATA_DIR.'/Base_Theme/templates/default/');
		return;
	}
	mkdir(DATA_DIR.'/Base_Theme/templates/default/'.$f);
	$content = scandir($dir.$f);
	foreach ($content as $name){
		if ($name == '.' || $name == '..') continue;
		$path = $dir.$f.'/'.$name;
		if (is_dir($path))
			install_default_theme_common_files($dir,$f.'/'.$name);
		else
			copy($path,DATA_DIR.'/Base_Theme/templates/default/'.$f.'/'.$name);
	}
}

function langup(){
	$ret = DB::Execute('SELECT * FROM modules');
	while($row = $ret->FetchRow()) {
		$mod_name = $row[0];
		if ($mod_name=='Base') continue;
		if ($mod_name=='Tests') continue;
		global $translations;
		$directory = 'modules/'.str_replace('_','/',$mod_name).'/lang';
		if (!is_dir($directory)) continue;
		$content = scandir($directory);
		$trans_backup = $translations;
		foreach ($content as $name){
			if($name == '.' || $name == '..' || ereg('^[\.~]',$name)) continue;
			$dot = strpos($name,'.');
			$langcode = substr($name,0,$dot);
			if (strtolower(substr($name,$dot+1))!='php') continue;
			$translations = array();
			@include(DATA_DIR.'/Base_Lang/'.$langcode.'.php');
			include($directory.'/'.$name);
			Base_LangCommon::save($langcode);
		}
		$translations = $trans_backup;
	}	
}

function themeup(){
	$data_dir = DATA_DIR.'/Base_Theme/templates/default/';
	$content = scandir($data_dir);
	foreach ($content as $name){
		if ($name == '.' || $name == '..') continue;
		recursive_rmdir($data_dir.$name);
	}

	$ret = DB::Execute('SELECT * FROM modules');
	while($row = $ret->FetchRow()) {
		$directory = 'modules/'.str_replace('_','/',$row[0]).'/theme_'.$row['version'];
		if (!is_dir($directory)) $directory = 'modules/'.str_replace('_','/',$row[0]).'/theme';
		$mod_name = $row[0];
		$data_dir = DATA_DIR.'/Base_Theme/templates/default';
		if (!is_dir($directory)) continue;
		$content = scandir($directory);

		$mod_name = str_replace('_','/',$mod_name);
		$mod_path = explode('/',$mod_name);
		$sum = '';
		foreach ($mod_path as $p) {
			$sum .= '/'.$p;
			@mkdir($data_dir.$sum);
		}
		foreach ($content as $name){
			if($name == '.' || $name == '..' || ereg('^[\.~]',$name)) continue;
			recursive_copy($directory.'/'.$name,$data_dir.'/'.$mod_name.'/'.$name);
		}
	}

	install_default_theme_common_files('modules/Base/Theme/','images');
}

$versions = array('0.8.5','0.8.6','0.8.7','0.8.8','0.8.9','0.8.10','0.8.11','0.9.0','0.9.1','0.9.9beta1','0.9.9beta2','1.0.0rc1','1.0.0rc2','1.0.0rc3','1.0.0rc4');

/****************** 0.8.5 to 0.8.6 **********************/
function update_from_0_9_9beta1_to_0_9_9beta2() {
	trigger_error('You cannot update to 0.9.9beta2. This version is next "make world".',E_USER_ERROR);
}

function update_from_0_9_9beta2_to_1_0_0rc1() {
	define('CID',false);
	require_once('include.php');
	//attachment
	ob_start();
	ModuleManager::load_modules();
	ModuleManager::install('Utils_Attachment_Administrator');
	ob_end_clean();
	//RB 1.01
	DB::CreateTable('recordbrowser_addon',
			'tab C(64),'.
			'module C(128),'.
			'func C(128),'.
			'label C(64)',
			array('constraints'=>', PRIMARY KEY(module, func)'));
	$ret = DB::Execute('SELECT tab FROM recordbrowser_table_properties');
	while($row = $ret->FetchRow()) {
		$ret2 = DB::Execute('SELECT module, func, label FROM '.$row['tab'].'_addon');
		while($row2 = $ret2->FetchRow()) {
			DB::Execute('INSERT INTO recordbrowser_addon (tab, module, func, label) VALUES (%s, %s, %s, %s)', array($row['tab'], $row2['module'], $row2['func'], $row2['label']));
		}
		DB::DropTable($row['tab'].'_addon');
	}

	//RB 1.02
	$ret = DB::Execute('SELECT tab FROM recordbrowser_table_properties');
	while($row = $ret->FetchRow()) {
		DB::Execute('ALTER TABLE '.$row['tab'].'_field ADD COLUMN style VARCHAR(64)');
		DB::Execute('UPDATE '.$row['tab'].'_field SET style=type WHERE type=%s or type=%s', array('timestamp','currency'));
		if ($row['tab']=='contact') DB::Execute('UPDATE '.$row['tab'].'_field SET param=\'company::Company Name;::\' WHERE field=\'Company Name\'');
		if ($row['tab']=='phonecall') DB::Execute('UPDATE '.$row['tab'].'_field SET param=\'company::Company Name;::\' WHERE field=\'Company Name\'');
	}

	//dashboard colors
	$q = DB::dict()->AddColumnSQL('base_dashboard_applets','color I2 DEFAULT 0');
	DB::Execute($q[0]);
	$q = DB::dict()->AddColumnSQL('base_dashboard_default_applets','color I2 DEFAULT 0');
	DB::Execute($q[0]);

	//tasks
	if(ModuleManager::is_installed('Utils_Tasks')>=0) {
		$q = DB::dict()->DropColumnSQL('utils_tasks_task','parent_module');
		DB::Execute($q[0]);
	}

	themeup();
}

function update_from_1_0_0rc1_to_1_0_0rc2() {
	DB::DropTable('history');
	DB::DropTable('session_client');
	DB::DropTable('session');
	DB::CreateTable('session',"name C(32) NOTNULL," .
			"expires I NOTNULL DEFAULT 0, data B",array('constraints'=>', PRIMARY KEY(name)'));
	DB::CreateTable('session_client',"session_name C(32) NOTNULL, client_id I2," .
			"data B",array('constraints'=>', FOREIGN KEY(session_name) REFERENCES session(name), PRIMARY KEY(client_id,session_name)'));
	DB::CreateTable('history',"session_name C(32) NOTNULL, page_id I, client_id I2," .
			"data B",array('constraints'=>', FOREIGN KEY(session_name) REFERENCES session(name), PRIMARY KEY(client_id,session_name,page_id), INDEX(session_name,client_id), INDEX(session_name)'));

	$q = DB::dict()->AddColumnSQL('utils_commondata_tree','readonly I1 DEFAULT 0');
	DB::Execute($q[0]);

	define('CID',false);
	require_once('include.php');
	ob_start();
	ModuleManager::load_modules();
	if(ModuleManager::is_installed('CRM/Contacts')>=0)
		ModuleManager::install('CRM_Followup');
	ob_end_clean();

	if(ModuleManager::is_installed('CRM/Calendar')>=0) {
		$q = DB::dict()->AddColumnSQL('crm_calendar_event','status I2 DEFAULT 0');
		DB::Execute($q[0]);
	}

	Utils_CommonDataCommon::new_array('Ticket_Status',array('Open','In Progress','Closed','Canceled'), true,true);
	Utils_CommonDataCommon::extend_array('Companies_Groups',array('rental company'=>'Rental Company'));

	$id = Utils_CommonDataCommon::get_id('Project_Status');
	if($id!==false)
		Utils_CommonDataCommon::extend_array('Project_Status',array('itb_received'=>'ITB Received','proposal_submited'=>'Proposal Submitted','job_canceled'=>'Job Canceled','job_lost'=>'Job Lost','job_awarded'=>'Job Awarded','on_hold'=>'On Hold','in_progress'=>'In Progress','completed_unpaid'=>'Completed Unpaid','paid'=>'Paid'),true,true);
	$id = Utils_CommonDataCommon::get_id('Job_Type');
	if($id!==false)
		Utils_CommonDataCommon::extend_array('Job_Type',array('Commercial','Residential','Maintenance'),true,true);
	$id = Utils_CommonDataCommon::get_id('Companies_Groups');
	if($id!==false) {
		Utils_CommonDataCommon::extend_array('Companies_Groups',array('customer'=>'Customer','vendor'=>'Vendor','other'=>'Other'),true,true);
		Utils_CommonDataCommon::extend_array('Companies_Groups',array('gc'=>'General Contractor','res'=>'Residential'),true,true);
	}
	$id = Utils_CommonDataCommon::get_id('Contacts_Groups');
	if($id!==false)
		Utils_CommonDataCommon::extend_array('Contacts_Groups',array('office'=>'Office Staff','field'=>'Field Staff','custm'=>'Customer'),true,true);
	$id = Utils_CommonDataCommon::get_id('Permissions');
	if($id!==false)
		Utils_CommonDataCommon::extend_array('Permissions',array('Public','Protected','Private'), true,true);
	$id = Utils_CommonDataCommon::get_id('Ticket_Status');
	if($id!==false)
		Utils_CommonDataCommon::extend_array('Ticket_Status',array('Open','In Progress','Closed','Canceled'), true,true);
	$id = Utils_CommonDataCommon::get_id('Priorities');
	if($id!==false)
		Utils_CommonDataCommon::extend_array('Priorities',array('Low','Medium','High'), true,true);
	$id = Utils_CommonDataCommon::get_id('Bugtrack_Status');
	if($id!==false)
		Utils_CommonDataCommon::extend_array('Bugtrack_Status',array('new'=>'New','inprog'=>'In Progress','cl'=>'Closed'),true,true);


	if(ModuleManager::is_installed('CRM_PhoneCall')>=0) {
		Acl::add_aco('CRM_PhoneCall','view protected notes','Employee');
		Acl::add_aco('CRM_PhoneCall','view public notes','Employee');
		Acl::add_aco('CRM_PhoneCall','edit protected notes','Employee Administrator');
		Acl::add_aco('CRM_PhoneCall','edit public notes','Employee');
	}
	//tasks
	if(ModuleManager::is_installed('Utils_Tasks')>=0) {
		Acl::add_aco('Utils_Tasks','view protected notes','Employee');
		Acl::add_aco('Utils_Tasks','view public notes','Employee');
		Acl::add_aco('Utils_Tasks','edit protected notes','Employee Administrator');
		Acl::add_aco('Utils_Tasks','edit public notes','Employee');

		$fields = array(
			array('name'=>'Title', 				'type'=>'text', 'required'=>true, 'param'=>'255', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('Utils_TasksCommon','display_title')),

			array('name'=>'Description', 		'type'=>'long text', 'extra'=>false, 'param'=>'255', 'visible'=>false),

			array('name'=>'Employees', 			'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('Utils_TasksCommon','employees_crits'), 'format'=>array('Utils_TasksCommon','contact_format_with_balls')), 'display_callback'=>array('Utils_TasksCommon','display_employees'), 'required'=>true, 'extra'=>false, 'visible'=>true),
			array('name'=>'Customers', 			'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('Utils_TasksCommon','customers_crits')), 'required'=>true, 'extra'=>false, 'visible'=>true),

			array('name'=>'Status',				'type'=>'select', 'required'=>true, 'visible'=>true, 'filter'=>true, 'param'=>'__COMMON__::Ticket_Status', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('Utils_TasksCommon','display_status')),
			array('name'=>'Priority', 			'type'=>'select', 'required'=>true, 'visible'=>true, 'param'=>'__COMMON__::Priorities', 'extra'=>false),
			array('name'=>'Permission', 		'type'=>'select', 'required'=>true, 'param'=>'__COMMON__::Permissions', 'extra'=>false),

			array('name'=>'Longterm',			'type'=>'checkbox', 'extra'=>false, 'filter'=>true, 'visible'=>true),

			array('name'=>'Is Deadline',		'type'=>'checkbox', 'extra'=>false, 'QFfield_callback'=>array('Utils_TasksCommon','QFfield_is_deadline')),
			array('name'=>'Deadline',			'type'=>'date', 'extra'=>false, 'visible'=>true),

			array('name'=>'Page id',			'type'=>'hidden', 'extra'=>false)

		);
		Utils_RecordBrowserCommon::install_new_recordset('task', $fields);
		Utils_RecordBrowserCommon::set_tpl('task', Base_ThemeCommon::get_template_filename('Utils/Tasks', 'default'));
		Utils_RecordBrowserCommon::set_processing_method('task', array('Utils_TasksCommon', 'submit_task'));
		Utils_RecordBrowserCommon::set_icon('task', Base_ThemeCommon::get_template_filename('Utils/Tasks', 'icon.png'));
		Utils_RecordBrowserCommon::set_recent('task', 5);
		Utils_RecordBrowserCommon::set_caption('task', 'Tasks');
		Utils_RecordBrowserCommon::set_access_callback('task', 'Utils_TasksCommon', 'access_task');
		Utils_RecordBrowserCommon::new_addon('task', 'Utils/Tasks', 'task_attachment_addon', 'Notes');

		Utils_CommonDataCommon::new_array('Ticket_Status',array('Open','In Progress','Closed'), true);
		Utils_CommonDataCommon::new_array('Permissions',array('Public','Protected','Private'), true);
		Utils_CommonDataCommon::new_array('Priorities',array('Low','Medium','High'), true);

		$ret = DB::CreateTable('task_employees_notified','
			task_id I4 NOTNULL,
			contact_id I4 NOTNULL',
			array('constraints'=>', FOREIGN KEY (task_id) REFERENCES task(ID), FOREIGN KEY (contact_id) REFERENCES contact(ID)'));

		//**************  move data ******************

		$tab = 'task';
		$table_rows = array();
		$ret = DB::Execute('SELECT * FROM '.$tab.'_field WHERE active=1 AND type!=\'page_split\' ORDER BY position');
		while($row = $ret->FetchRow()) {
			if ($row['field']=='id') continue;
			$table_rows[$row['field']] =
				array(	'name'=>$row['field'],
						'id'=>strtolower(str_replace(' ','_',$row['field'])),
						'type'=>$row['type'],
						'visible'=>$row['visible'],
						'required'=>$row['required'],
						'extra'=>$row['extra'],
						'active'=>$row['active'],
						'position'=>$row['position'],
						'filter'=>$row['filter'],
						'style'=>$row['style'],
						'param'=>$row['param']);
		}

		if(DATABASE_DRIVER=='mysqlt' || DATABASE_DRIVER=='mysqli')
			DB::Execute('SET FOREIGN_KEY_CHECKS=0');
		$ret = DB::Execute('SELECT * FROM utils_tasks_task');
		while ($row = $ret->FetchRow()) {
			$employees = DB::GetAssoc('SELECT contact, contact FROM crm_calendar_event_group_emp AS ccegp WHERE ccegp.id=%d', array($row['id']));
			$customers = DB::GetAssoc('SELECT contact, contact FROM crm_calendar_event_group_cus AS ccegc WHERE ccegc.id=%d', array($row['id']));
				$values = array(	'title'=>$row['title'],
									'description'=>$row['description'],
									'priority'=>$row['priority'],
									'deadline'=>$row['deadline'],
									'is_deadline'=>($row['deadline']!=null),
									'status'=>$row['status'],
									'longterm'=>$row['longterm'],
									'page_id'=>$row['page_id'],
									'permission'=>$row['permission'],
									'employees'=>$employees,
									'customers'=>$customers
								);
		//	$id = Utils_RecordBrowserCommon::new_record('task', $rec);
				DB::StartTrans();
				$id = $row['id'];
				DB::Execute('INSERT INTO '.$tab.' (id, created_on, created_by, active) VALUES (%d, %T, %d, %d)',array($id, $row['created_on'], $row['created_by'], 1));
				foreach($table_rows as $field => $args) {
					if (!isset($values[$args['id']]) || $values[$args['id']]=='') continue;
					if (!is_array($values[$args['id']]))
						DB::Execute('INSERT INTO '.$tab.'_data ('.$tab.'_id, field, value) VALUES (%d, %s, %s)',array($id, $field, $values[$args['id']]));
					else
						foreach($values[$args['id']] as $v)
							DB::Execute('INSERT INTO '.$tab.'_data ('.$tab.'_id, field, value) VALUES (%d, %s, %s)',array($id, $field, $v));
				}
				DB::CompleteTrans();
		}

		$ret = DB::Execute('SELECT * FROM utils_tasks_assigned_contacts WHERE viewed=1');
		while ($row = $ret->FetchRow()) {
			DB::Execute('INSERT INTO task_employees_notified (task_id, contact_id) VALUES (%d, %d)', array($row['task_id'], $row['contact_id']));
		}

		//************* delete old tables ******************
		DB::DropTable('utils_tasks_related_contacts');
		DB::DropTable('utils_tasks_assigned_contacts');
		DB::DropTable('utils_tasks_task');

		if(DATABASE_DRIVER=='mysqlt' || DATABASE_DRIVER=='mysqli')
			DB::Execute('SET FOREIGN_KEY_CHECKS=1');

	}

	themeup();
}

function update_from_1_0_0rc2_to_1_0_0rc3() {
	define('CID',false);
	require_once('include.php');
	ob_start();
	DB::Execute('DELETE FROM modules WHERE name="utils_tasks"');
	ModuleManager::load_modules();
	if (ModuleManager::is_installed('Utils/Watchdog')==-1) {
		ModuleManager::install('Utils_Watchdog',0,false);
		ModuleManager::include_common('Utils_Watchdog',0);
	}
	if (ModuleManager::is_installed('Libs/TCPDF')==-1) {
		ModuleManager::install('Libs_TCPDF',0,false);
	}
	if (ModuleManager::is_installed('Utils/RecordBrowser')!=-1 ||
		ModuleManager::is_installed('Base/HomePage')!=-1) {
		ModuleManager::install('Utils_Shortcut',0,false);
	}
	ModuleManager::create_load_priority_array();
	ob_end_clean();


	if (ModuleManager::is_installed('Utils/RecordBrowser')>=0) {
		DB::Execute('SET FOREIGN_KEY_CHECKS=0');

		$icons = DB::GetAssoc('SELECT tab, icon FROM recordbrowser_table_properties');
		foreach ($icons as $t=>$i) {
			$ic = explode('__', $i);
			if (isset($ic[1])) {
				$new_i = str_replace('_','/',$ic[0]).'/'.$ic[1];
				DB::Execute('UPDATE recordbrowser_table_properties SET icon=%s WHERE tab=%s', array($new_i, $t));
			}
		} 
		$icons = DB::GetAssoc('SELECT tab, tpl FROM recordbrowser_table_properties');
		foreach ($icons as $t=>$i) {
			$ic = explode('__', $i);
			if (isset($ic[1])) {
				$new_i = str_replace('_','/',$ic[0]).'/'.$ic[1];
				DB::Execute('UPDATE recordbrowser_table_properties SET tpl=%s WHERE tab=%s', array($new_i, $t));
			}
		} 

		$tabs = DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties');
		foreach ($tabs as $t) {
			foreach (array('recent', 'favorite', 'edit_history') as $v){
				if(DATABASE_DRIVER=='postgres') {
					$idxs = DB::Execute('SELECT t.tgargs as args FROM pg_trigger t,pg_class c,pg_proc p WHERE t.tgenabled AND t.tgrelid = c.oid AND t.tgfoid = p.oid AND p.proname = \'RI_FKey_check_ins\' AND c.relname = \''.strtolower($t.'_'.$v).'\' ORDER BY t.tgrelid');
					$matches = array(1=>array());
					while ($i = $idxs->FetchRow()) {
						$data = explode(chr(0), $i[0]);
						$matches[1][] = $data[0];
					}
					$op = 'CONSTRAINT';
				} else {
					$a_create_table = DB::getRow(sprintf('SHOW CREATE TABLE %s', $t.'_'.$v));
					$create_sql  = $a_create_table[1];
					if (!preg_match_all("/CONSTRAINT `(.*?)` FOREIGN KEY \(`(.*?)`\) REFERENCES `(.*?)` \(`(.*?)`\)/", $create_sql, $matches)) continue;
					$op = 'FOREIGN KEY';
				}
				$num_keys = count($matches[1]);
				for ( $i = 0;  $i < $num_keys;  $i ++ ) {
					DB::Execute('ALTER TABLE '.$t.'_'.$v.' DROP '.$op.' '.$matches[1][$i]);
				}
			}
		}
		foreach ($tabs as $t) {
			foreach (array('recent', 'favorite', 'edit_history') as $v){
				@DB::CreateIndex($t.'_'.$v.'__start__idx', $t.'_'.$v, $t.'_id');
			}
		}

		$tabs = DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties');
		foreach ($tabs as $t) {
			$fields = DB::GetAssoc('SELECT field, param FROM '.$t.'_field WHERE type="multiselect" OR type="select"');
			foreach ($fields as $f=>$p) {
				DB::Execute('UPDATE '.$t.'_field SET param=%s WHERE field=%s', array(str_replace('First Name|Last Name','Last Name|First Name',$p),$f));
			}
		}

		DB::Execute('SET FOREIGN_KEY_CHECKS=1');
	}


	if (ModuleManager::is_installed('Utils/Attachment')>=0) {
		if (PatchDBAddColumn('utils_attachment_link','sticky','I1 DEFAULT 0')){
			DB::Execute('UPDATE utils_attachment_link SET sticky=0');
		}
	}





	if (ModuleManager::is_installed('CRM/Calendar/Event')>=0) {
		PatchDBAddColumn('crm_calendar_event','recurrence_id','I4');
		PatchDBRenameColumn('crm_calendar_event','start','starts','I4 NOT NULL');
		PatchDBRenameColumn('crm_calendar_event','end','ends','I4 NOT NULL');
		PatchDBDropColumn('crm_calendar_event','recurrence_id');

		PatchDBAddColumn('crm_calendar_event','recurrence_type','I2');
		PatchDBAddColumn('crm_calendar_event','recurrence_end','D');
		PatchDBAddColumn('crm_calendar_event','recurrence_hash','C(8)');

		Utils_WatchdogCommon::register_category('crm_calendar', array('CRM_CalendarCommon','watchdog_label'));
	}





	if (ModuleManager::is_installed('CRM/PhoneCall')>=0) {
		Utils_RecordBrowserCommon::enable_watchdog('phonecall', array('CRM_PhoneCallCommon','watchdog_label'));
	}





	if (ModuleManager::is_installed('CRM/Contacts')>=0) {
		DB::Execute('UPDATE contact_field SET filter=1 WHERE field=%s',array('Group'));
		DB::Execute('UPDATE contact_field SET filter=1 WHERE field=%s',array('Company Name'));
		DB::Execute('UPDATE company_field SET filter=1 WHERE field=%s',array('Group'));

		Utils_RecordBrowserCommon::set_display_method('company', 'Address 1', 'CRM_ContactsCommon', 'maplink');
		Utils_RecordBrowserCommon::set_display_method('company', 'Address 2', 'CRM_ContactsCommon', 'maplink');
		Utils_RecordBrowserCommon::set_display_method('company', 'City', 'CRM_ContactsCommon', 'maplink');
		Utils_RecordBrowserCommon::set_display_method('contact', 'Address 1', 'CRM_ContactsCommon', 'maplink');
		Utils_RecordBrowserCommon::set_display_method('contact', 'Address 2', 'CRM_ContactsCommon', 'maplink');
		Utils_RecordBrowserCommon::set_display_method('contact', 'City', 'CRM_ContactsCommon', 'maplink');

		Utils_RecordBrowserCommon::enable_watchdog('company', array('CRM_ContactsCommon','company_watchdog_label'));
		Utils_RecordBrowserCommon::enable_watchdog('contact', array('CRM_ContactsCommon','contact_watchdog_label'));
	}







	if (ModuleManager::is_installed('Utils/Tasks')>=0) {
		Utils_RecordBrowserCommon::delete_addon('task', 'Utils/Tasks', 'task_attachment_addon');
		Utils_RecordBrowserCommon::new_addon('task', 'CRM/Tasks', 'task_attachment_addon', 'Notes');
		DB::Execute('UPDATE task_field SET param="contact::First Name|Last Name;CRM_TasksCommon::contact_format_with_balls;CRM_TasksCommon::employees_crits" WHERE field="Employees"');
		DB::Execute('UPDATE task_field SET param="contact::First Name|Last Name;::;CRM_TasksCommon::customers_crits" WHERE field="Customers"');
		DB::Execute('UPDATE recordbrowser_addon SET module="CRM_Tasks" WHERE tab="task"');
		DB::Execute('UPDATE task_callback SET module="CRM_TasksCommon" WHERE module="Utils_TasksCommon"');
		DB::Execute('UPDATE recordbrowser_table_properties SET tpl="CRM_Tasks__default", icon="CRM_Tasks__icon.png", access_callback="CRM_TasksCommon::access_task", data_process_method="CRM_TasksCommon::submit_task" WHERE tab="task"');
		DB::Execute('DELETE FROM task_data WHERE field="Page id"');
		DB::Execute('DELETE FROM task_field WHERE field="Page id"');
		Acl::add_aco('CRM_Tasks','browse tasks',array('Employee'));
		Acl::add_aco('CRM_Tasks','view task',array('Employee'));
		Acl::add_aco('CRM_Tasks','edit task',array('Employee'));
		Acl::add_aco('CRM_Tasks','delete task',array('Employee Manager'));
		Acl::del_aco('Utils_Tasks','browse tasks');
		Acl::del_aco('Utils_Tasks','view task');
		Acl::del_aco('Utils_Tasks','edit task');
		Acl::del_aco('Utils_Tasks','delete task');

		Acl::add_aco('CRM_Tasks','view protected notes','Employee');
		Acl::add_aco('CRM_Tasks','view public notes','Employee');
		Acl::add_aco('CRM_Tasks','edit protected notes','Employee Administrator');
		Acl::add_aco('CRM_Tasks','edit public notes','Employee');
		Acl::del_aco('Utils_Tasks','view protected notes','Employee');
		Acl::del_aco('Utils_Tasks','view public notes','Employee');
		Acl::del_aco('Utils_Tasks','edit protected notes','Employee Administrator');
		Acl::del_aco('Utils_Tasks','edit public notes','Employee');

		Utils_RecordBrowserCommon::enable_watchdog('task', array('CRM_TasksCommon','watchdog_label'));
		DB::DropTable('task_employees_notified');
		DB::Execute('UPDATE task_field SET param=\'contact::First Name|Last Name;CRM_ContactsCommon::contact_format_no_company;CRM_TasksCommon::employees_crits\' WHERE field=\'Employees\'');
	}


	if (ModuleManager::is_installed('Utils/RecordBrowser')==0) {
		@set_time_limit(0);
		ini_set("memory_limit","512M");
		
		// Create RB update table
		$tables_db = DB::MetaTables();
		if(!in_array('patch_rb',$tables_db))
			DB::CreateTable('patch_rb',"id C(32) KEY NOTNULL");
		
		$tabs = DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties');
		foreach ($tabs as $t) {
			// skip upgrade if the table was already upgraded
			if (DB::GetOne('SELECT 1 FROM patch_rb WHERE id=%s',array($t))) continue;
			if (!in_array($t,$tables_db)) continue;
		
			@DB::CreateTable($t.'_data_1',
						'id I AUTO KEY,'.
						'created_on T NOT NULL,'.
						'created_by I NOT NULL,'.
						'private I4 DEFAULT 0,'.
						'active I1 NOT NULL DEFAULT 1',
						array('constraints'=>''));
			foreach (array('recent', 'favorite', 'edit_history') as $v){
				if(DATABASE_DRIVER=='postgres') {
					$idxs = DB::Execute('SELECT t.tgargs as args FROM pg_trigger t,pg_class c,pg_proc p WHERE t.tgenabled AND t.tgrelid = c.oid AND t.tgfoid = p.oid AND p.proname = \'RI_FKey_check_ins\' AND c.relname = \''.strtolower($t.'_'.$v).'\' ORDER BY t.tgrelid');
					$matches = array(1=>array());
					while ($i = $idxs->FetchRow()) {
						$data = explode(chr(0), $i[0]);
						$matches[1][] = $data[0];
					}
					$op = 'CONSTRAINT';
				} else { 
					$a_create_table = DB::getRow(sprintf('SHOW CREATE TABLE %s', $t.'_'.$v));
				    $create_sql  = $a_create_table[1];
				    if (!preg_match_all("/CONSTRAINT `(.*?)` FOREIGN KEY \(`(.*?)`\) REFERENCES `(.*?)` \(`(.*?)`\)/", $create_sql, $matches)) continue;
				    $op = 'FOREIGN KEY';
				}
				$num_keys = count($matches[1]);
			    for ( $i = 0;  $i < $num_keys;  $i ++ ) {
					DB::Execute('ALTER TABLE '.$t.'_'.$v.' DROP '.$op.' '.$matches[1][$i]);
			    }
			}
			$cols = DB::Execute('SELECT field, type, param FROM '.$t.'_field WHERE type!=%s AND type!=%s', array('foreign index','page_split'));
			$table_rows = array();
			while ($c = $cols->FetchRow()) {
				switch ($c['type']) {
					case 'text': $f = DB::dict()->ActualType('C').'('.$c['param'].')'; break;
					case 'select': $f = DB::dict()->ActualType('X'); break;
					case 'multiselect': $f = DB::dict()->ActualType('X'); break;
					case 'commondata': $f = DB::dict()->ActualType('C').'(128)'; break;
					case 'integer': $f = DB::dict()->ActualType('F'); break;
					case 'date': $f = DB::dict()->ActualType('D'); break;
					case 'timestamp': $f = DB::dict()->ActualType('T'); break;
					case 'long text': $f = DB::dict()->ActualType('X'); break;
					case 'hidden': $f = (isset($c['param'])?$c['param']:''); break;
					case 'calculated': $f = (isset($c['param'])?$c['param']:''); break;
					case 'checkbox': $f = DB::dict()->ActualType('I1'); break;
					case 'currency': $f = DB::dict()->ActualType('C').'(128)'; break;
				}
				$table_rows[$c['field']] = array('type'=>$c['type'], 'param'=>$c['param']);
				if (!isset($f)) trigger_error('Database column for type '.$c['type'].' undefined.',E_USER_ERROR);
				if ($f!=='') DB::Execute('ALTER TABLE '.$t.'_data_1 ADD COLUMN f_'.strtolower(str_replace(' ','_',$c['field'])).' '.$f);
			}
			$params = DB::GetAssoc('SELECT field, type FROM '.$t.'_field');
			$multi = array();
			$rest = '';
			foreach($params as $k=>$v) {
				if ($v=='multiselect') $multi[] = $k;
				else $rest .= ' OR field=\''.$k.'\'';
			} 
			$recs = DB::Execute('SELECT * FROM '.$t);
			while ($r = $recs->FetchRow()) {
				DB::Execute('INSERT INTO '.$t.'_data_1 (id, active, created_by, created_on) VALUES (%d, %d, %d, %T)', array($r['id'], $r['active'], $r['created_by'], $r['created_on']));
				foreach($multi as $v) {
					$vals = DB::GetAssoc('SELECT value, value FROM '.$t.'_data WHERE field=%s AND '.$t.'_id=%d',array($v,$r['id']));
					if (empty($vals)) continue;
					DB::Execute('UPDATE '.$t.'_data_1 SET f_'.strtolower(str_replace(' ','_',$v)).'='.DB::qstr('__'.implode('__',$vals).'__').' WHERE id='.$r['id']);
				}
				$vals = DB::GetAssoc('SELECT field, value FROM '.$t.'_data WHERE '.$t.'_id='.$r['id'].' AND (false'.$rest.')');
				$update = '';
				foreach ($vals as $k=>$v) {
					if ($table_rows[$k]['type']=='text') $v=substr($v, 0, $table_rows[$k]['param']);
					if ($table_rows[$k]['type']=='integer') $v = floatval($v);
					DB::Execute('UPDATE '.$t.'_data_1 SET f_'.strtolower(str_replace(' ','_',$k)).'='.DB::qstr($v).' WHERE id='.$r['id']);					
				}
			}
			if (!empty($multi)) {
				$field = '';
				$vals = array();
				foreach ($multi as $v) {
					$field .= ' OR field=%s';
					$vals[] = str_replace(' ','_',strtolower($v));
				}
				$ret = DB::Execute('SELECT edit_id, field, old_value FROM '.$t.'_edit_history_data WHERE (false'.$field.') ORDER BY field ASC, edit_id ASC',$vals);
				$l_eid = -1;
				$l_f = '';
				$values = array();
		
				$row = $ret->FetchRow();
				if ($row) {
					$l_f = $row['field'];
					$l_eid = $row['edit_id'];
					while ($row) {
						$values[] = $row['old_value'];
						$row = $ret->FetchRow();
						if ($l_f!=$row['field'] || $l_eid!=$row['edit_id']) {
							if (count($values)==1) {
								$values = array(trim($values[0], '_'));
							} 
							if (count($values)==1 && $values[0]=='') $insert = ''; 
							else $insert = '__'.implode('__',$values).'__';
							DB::Execute('DELETE FROM '.$t.'_edit_history_data WHERE field=%s AND edit_id=%d', array($l_f, $l_eid));
							DB::Execute('INSERT INTO '.$t.'_edit_history_data(edit_id,field,old_value) VALUES (%d, %s, %s)', array($l_eid, $l_f, $insert));
							$values = array();
							$l_f = $row['field'];
							$l_eid = $row['edit_id'];
						}
					}
				}
			}
			DB::Execute('INSERT INTO patch_rb VALUES(%s)',array($t));
			@DB::DropTable($t.'_data');
			@DB::DropTable($t);
		}
		@DB::DropTable('patch_rb');
	
	}


	ModuleManager::create_common_cache();
	themeup();
	langup();
	Base_ThemeCommon::create_cache();
}

function update_from_1_0_0rc3_to_1_0_0rc4() {
	define('CID',false);
	require_once('include.php');
	ob_start();
	ModuleManager::load_modules();
	ob_end_clean();

	if (ModuleManager::is_installed('Base/User/Login')>=0) {
		PatchDBAddColumn('user_password','mobile_autologin_id','C(32)');
	}

	ModuleManager::create_common_cache();
	themeup();
	langup();
	Base_ThemeCommon::create_cache();
}

//=========================================================================

try {
$cur_ver = Variable::get('version');
} catch(Exception $s) {
$cur_ver = '0.8.5';
}
$go=false;
$last_ver = '';
foreach($versions as $v) {
	$x = str_replace('.','_',$v);
	if($go) {
		if(is_callable('update_from_'.$last_ver.'_to_'.$x)) {
//			print('Update from '.$last_ver.' to '.$x.'<br>');
			call_user_func('update_from_'.$last_ver.'_to_'.$x);
		}
	}
	if($v==$cur_ver) $go=true;
	if($v==EPESI_VERSION) $go=false;
	$last_ver = $x;
}
Variable::set('version',EPESI_VERSION);
?>
