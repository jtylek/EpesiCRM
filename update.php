<?php
/**
 * EPESI Core updater.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license MIT
 * @package epesi-base
 */
defined("_VALID_ACCESS") || define("_VALID_ACCESS", true);

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

function PatchDBAlterColumn($table_name,$table_column_name,$table_column_def){
	// First check if column exists
	if (array_key_exists(strtoupper($table_column_name),DB::MetaColumnNames($table_name)))
		{
		$q = DB::dict()->AlterColumnSQL($table_name,$table_column_name.' '.$table_column_def);
		foreach($q as $qq)
		DB::Execute($qq);
		return true;
		}
		else
		{
		return false;
		}
	} //end of DBAlterColumn

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
			if($name == '.' || $name == '..' || preg_match('/^[\.~]/',$name)) continue;
			recursive_copy($directory.'/'.$name,$data_dir.'/'.$mod_name.'/'.$name);
		}
	}

	install_default_theme_common_files('modules/Base/Theme/','images');
}

$versions = array('0.8.5','0.8.6','0.8.7','0.8.8','0.8.9','0.8.10','0.8.11','0.9.0','0.9.1','0.9.9beta1','0.9.9beta2','1.0.0rc1','1.0.0rc2','1.0.0rc3','1.0.0rc4','1.0.0rc5','1.0.0rc6','1.0.0','1.0.1','1.0.2','1.0.3','1.0.4','1.0.5','1.0.6','1.0.7','1.0.8','1.0.8b','1.0.9','1.1.0','1.1.1','1.1.2');

/****************** 0.8.5 to 0.8.6 **********************/
function update_from_0_9_9beta1_to_0_9_9beta2() {
	trigger_error('You cannot update to 0.9.9beta2. This version is next "make world".',E_USER_ERROR);
}

function update_from_0_9_9beta2_to_1_0_0rc1() {
	//attachment
	ob_start();
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

	ob_start();
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


	//tasks
	if(ModuleManager::is_installed('Utils_Tasks')>=0) {

		$fields = array(
			array('name' => 'Title', 				'type'=>'text', 'required'=>true, 'param'=>'255', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('Utils_TasksCommon','display_title')),

			array('name' => 'Description', 		'type'=>'long text', 'extra'=>false, 'param'=>'255', 'visible'=>false),

			array('name' => 'Employees', 			'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('Utils_TasksCommon','employees_crits'), 'format'=>array('Utils_TasksCommon','contact_format_with_balls')), 'display_callback'=>array('Utils_TasksCommon','display_employees'), 'required'=>true, 'extra'=>false, 'visible'=>true),
			array('name' => 'Customers', 			'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('Utils_TasksCommon','customers_crits')), 'required'=>true, 'extra'=>false, 'visible'=>true),

			array('name' => 'Status',				'type'=>'select', 'required'=>true, 'visible'=>true, 'filter'=>true, 'param'=>'__COMMON__::Ticket_Status', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('Utils_TasksCommon','display_status')),
			array('name' => 'Priority', 			'type'=>'select', 'required'=>true, 'visible'=>true, 'param'=>'__COMMON__::Priorities', 'extra'=>false),
			array('name' => 'Permission', 		'type'=>'select', 'required'=>true, 'param'=>'__COMMON__::Permissions', 'extra'=>false),

			array('name' => 'Longterm',			'type'=>'checkbox', 'extra'=>false, 'filter'=>true, 'visible'=>true),

			array('name' => 'Is Deadline',		'type'=>'checkbox', 'extra'=>false, 'QFfield_callback'=>array('Utils_TasksCommon','QFfield_is_deadline')),
			array('name' => 'Deadline',			'type'=>'date', 'extra'=>false, 'visible'=>true),

			array('name' => 'Page id',			'type'=>'hidden', 'extra'=>false)

		);
		Utils_RecordBrowserCommon::install_new_recordset('task', $fields);
		Utils_RecordBrowserCommon::set_tpl('task', Base_ThemeCommon::get_template_filename('Utils/Tasks', 'default'));
		Utils_RecordBrowserCommon::register_processing_callback('task', array('Utils_TasksCommon', 'submit_task'));
		Utils_RecordBrowserCommon::set_icon('task', Base_ThemeCommon::get_template_filename('Utils/Tasks', 'icon.png'));
		Utils_RecordBrowserCommon::set_recent('task', 5);
		Utils_RecordBrowserCommon::set_caption('task', 'Tasks');
		Utils_RecordBrowserCommon::new_addon('task', 'Utils/Tasks', 'task_attachment_addon', 'Notes');

		Utils_RecordBrowserCommon::add_access('task', 'view', 'ACCESS:employee', array('(!permission'=>2, '|employees'=>'USER'));
		Utils_RecordBrowserCommon::add_access('task', 'add', 'ACCESS:employee');
		Utils_RecordBrowserCommon::add_access('task', 'edit', 'ACCESS:employee', array('(permission'=>0, '|employees'=>'USER', '|customers'=>'USER'));
		Utils_RecordBrowserCommon::add_access('task', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
		Utils_RecordBrowserCommon::add_access('task', 'delete', array('ACCESS:employee','ACCESS:manager'));

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
			$fields = DB::GetAssoc('SELECT field, param FROM '.$t.'_field WHERE type=%s OR type=%s', array("multiselect", "select"));
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

		Utils_RecordBrowserCommon::set_display_callback('company', 'Address 1', array('CRM_ContactsCommon', 'maplink'));
		Utils_RecordBrowserCommon::set_display_callback('company', 'Address 2', array('CRM_ContactsCommon', 'maplink'));
		Utils_RecordBrowserCommon::set_display_callback('company', 'City', array('CRM_ContactsCommon', 'maplink'));
		Utils_RecordBrowserCommon::set_display_callback('contact', 'Address 1', array('CRM_ContactsCommon', 'maplink'));
		Utils_RecordBrowserCommon::set_display_callback('contact', 'Address 2', array('CRM_ContactsCommon', 'maplink'));
		Utils_RecordBrowserCommon::set_display_callback('contact', 'City', array('CRM_ContactsCommon', 'maplink'));

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
		DB::Execute('UPDATE recordbrowser_table_properties SET tpl="CRM_Tasks__default", icon="CRM_Tasks__icon.png" WHERE tab="task"');
		DB::Execute('DELETE FROM task_data WHERE field="Page id"');
		DB::Execute('DELETE FROM task_field WHERE field="Page id"');

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
}

function update_from_1_0_0rc3_to_1_0_0rc4() {
	if (ModuleManager::is_installed('Base/User/Login')>=0) {
		PatchDBAddColumn('user_password','mobile_autologin_id','C(32)');
	}
}

function update_from_1_0_0rc4_to_1_0_0rc5() {
	//addons management
	if (ModuleManager::is_installed('Utils_RecordBrowser')>=0) {
		PatchDBAddColumn('recordbrowser_addon','pos','I');
		PatchDBAddColumn('recordbrowser_addon','enabled','I1');
		PatchDBRenameColumn('recordbrowser_addon','label','label_','C(128)');
		PatchDBAddColumn('recordbrowser_addon','label','C(128)');
		DB::Execute('UPDATE recordbrowser_addon SET label=label_');
		PatchDBDropColumn('recordbrowser_addon','label_');

		DB::Execute('UPDATE recordbrowser_addon SET enabled=1, pos=0 WHERE pos IS NULL');
	
		$tab = DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties');
		foreach ($tab as $t) {
			do {
				$dangling_add = DB::GetOne('SELECT label FROM recordbrowser_addon WHERE tab=%s AND pos=0',array($t));
				if ($dangling_add) {
					$max = DB::GetOne('SELECT MAX(pos)+1 FROM recordbrowser_addon WHERE tab=%s', array($t));
					DB::Execute('UPDATE recordbrowser_addon SET pos=%d WHERE tab=%s AND pos=0 AND label=%s', array($max,$t,$dangling_add));
				}
			} while ($dangling_add);
		}
	}
	
	//bb codes
	ModuleManager::install('Utils_BBCode');
	DB::Execute('DELETE FROM utils_bbcode');
	DB::Execute('INSERT INTO utils_bbcode VALUES (%s, %s)', array('b','Utils_BBCodeCommon::tag_b'));
	DB::Execute('INSERT INTO utils_bbcode VALUES (%s, %s)', array('i','Utils_BBCodeCommon::tag_i'));
	DB::Execute('INSERT INTO utils_bbcode VALUES (%s, %s)', array('u','Utils_BBCodeCommon::tag_u'));
	DB::Execute('INSERT INTO utils_bbcode VALUES (%s, %s)', array('s','Utils_BBCodeCommon::tag_s'));
	DB::Execute('INSERT INTO utils_bbcode VALUES (%s, %s)', array('url','Utils_BBCodeCommon::tag_url'));
	DB::Execute('INSERT INTO utils_bbcode VALUES (%s, %s)', array('color','Utils_BBCodeCommon::tag_color'));
	DB::Execute('INSERT INTO utils_bbcode VALUES (%s, %s)', array('img','Utils_BBCodeCommon::tag_img'));

	if (ModuleManager::is_installed('CRM_Contacts')>=0) {
		Utils_BBCodeCommon::new_bbcode('contact', 'CRM_ContactsCommon', 'contact_bbcode');
		Utils_BBCodeCommon::new_bbcode('company', 'CRM_ContactsCommon', 'company_bbcode');
	}
	if (ModuleManager::is_installed('CRM_Tasks')>=0)
		Utils_BBCodeCommon::new_bbcode('task', 'CRM_TasksCommon', 'task_bbcode');
	if (ModuleManager::is_installed('Premium_Projects_Tickets')>=0)
		Utils_BBCodeCommon::new_bbcode('ticket', 'Premium_Projects_TicketsCommon', 'ticket_bbcode');

	//tasks fix
	if (ModuleManager::is_installed('CRM_Tasks')>=0)
		Utils_RecordBrowserCommon::delete_record_field('task','Is Deadline');

	//iphone callto
	if (ModuleManager::is_installed('CRM_Contacts')>=0) {
		Utils_RecordBrowserCommon::set_display_callback('company', 'Phone', array('CRM_ContactsCommon', 'display_phone'));
		Utils_RecordBrowserCommon::set_display_callback('contact', 'Work Phone', array('CRM_ContactsCommon', 'display_phone'));
		Utils_RecordBrowserCommon::set_display_callback('contact', 'Mobile Phone', array('CRM_ContactsCommon', 'display_phone'));
		Utils_RecordBrowserCommon::set_display_callback('contact', 'Home Phone', array('CRM_ContactsCommon', 'display_phone'));
	}

	//lang
	Base_LangCommon::refresh_cache();

	//user settings module foreign key
	$tabs = array('base_user_settings','base_user_settings_admin_defaults');
	foreach ($tabs as $t) {
		if(DATABASE_DRIVER=='postgres') {
			$idxs = DB::Execute('SELECT t.tgargs as args FROM pg_trigger t,pg_class c,pg_proc p WHERE t.tgenabled AND t.tgrelid = c.oid AND t.tgfoid = p.oid AND p.proname = \'RI_FKey_check_ins\' AND c.relname = \''.strtolower($t).'\' ORDER BY t.tgrelid');
			$matches = array(1=>array());
			while ($i = $idxs->FetchRow()) {
				$data = explode(chr(0), $i[0]);
				$matches[1][] = $data[0];
			}
			$op = 'CONSTRAINT';
		} else { 
			$a_create_table = DB::getRow(sprintf('SHOW CREATE TABLE %s', $t));
	    	$create_sql  = $a_create_table[1];
		    if (!preg_match_all("/CONSTRAINT `(.*?)` FOREIGN KEY \(`(.*?)`\) REFERENCES `(.*?)` \(`(.*?)`\)/", $create_sql, $matches)) continue;
		    $op = 'FOREIGN KEY';
		}
		$num_keys = count($matches[1]);
	    for ( $i = 0;  $i < $num_keys;  $i ++ ) {
			DB::Execute('ALTER TABLE '.$t.' DROP '.$op.' '.$matches[1][$i]);
	    }
	}
}

function update_from_1_0_0rc5_to_1_0_0rc6() {
	if (ModuleManager::is_installed('Utils_Attachment')>=0) {
		PatchDBAddColumn('utils_attachment_link','func','C(255)');
		PatchDBAddColumn('utils_attachment_link','args','C(255)');

		if (array_key_exists(strtoupper('attachment_key'),DB::MetaColumnNames('utils_attachment_link'))) {
			if (ModuleManager::is_installed('CRM_Tasks')>=0) {
				$task_group = 'CRM/Tasks/'.md5('crm_tasks');
				$tasks = CRM_TasksCommon::get_tasks();
				foreach($tasks as $t) {
					$ats = DB::GetAll('SELECT ual.id,uaf.revision FROM utils_attachment_link ual INNER JOIN utils_attachment_file uaf ON (uaf.attach_id=ual.id) WHERE ual.attachment_key=%s AND ual.local=%s',array(md5('Task:'.$t['id']),$task_group));
					$file_base = DATA_DIR.'/Utils_Attachment/'.$task_group.'/';
					$new_file_base = DATA_DIR.'/Utils_Attachment/CRM/Tasks/'.$t['id'].'/';
					foreach($ats as $a)
						@rename($file_base.$a['id'].'_'.$a['revision'],$new_file_base.$a['id'].'_'.$a['revision']);

					DB::Execute('UPDATE utils_attachment_link SET local=%s WHERE attachment_key=%s AND local=%s',array('CRM/Tasks/'.$t['id'],md5('Task:'.$t['id']),$task_group));
				}
			}
			PatchDBDropColumn('utils_attachment_link','attachment_key');
			DB::CreateIndex('utils_attachment_link__attachment__local__idx', 'utils_attachment_link', 'local');
		}

		PatchDBDropColumn('utils_attachment_link','other_read');

		if(ModuleManager::is_installed('Apps_Bugtrack')>=0)
			ModuleManager::uninstall('Apps_Bugtrack');
			
		if (ModuleManager::is_installed('CRM_Calendar')>=0) {
			$calendar_ats = DB::GetAssoc('SELECT ual.id,ual.local FROM utils_attachment_link ual WHERE ual.local LIKE \'CRM/Calendar/Event/%\'');

			foreach($calendar_ats as $i=>$g) {
				if(preg_match('/CRM\/Calendar\/Event\/([0-9]+)/',$g,$reqs))
					DB::Execute('UPDATE utils_attachment_link SET func=%s,args=%s WHERE id=%d',array(serialize(array('CRM_CalendarCommon','search_format')),serialize(array($reqs[1])),$i));
			}
		}
		
		if (ModuleManager::is_installed('CRM_Contacts')>=0) {
			$contact_ats = DB::GetAssoc('SELECT ual.id,ual.local FROM utils_attachment_link ual WHERE ual.local LIKE \'CRM/Contact/%\'');
			$company_ats = DB::GetAssoc('SELECT ual.id,ual.local FROM utils_attachment_link ual WHERE ual.local LIKE \'CRM/Company/%\'');

			foreach($contact_ats as $i=>$g) {
				if(preg_match('/CRM\/Contact\/([0-9]+)/',$g,$reqs))
					DB::Execute('UPDATE utils_attachment_link SET func=%s,args=%s WHERE id=%d',array(serialize(array('CRM_ContactsCommon','search_format_contact')),serialize(array($reqs[1])),$i));
			}

			foreach($company_ats as $i=>$g) {
				if(preg_match('/CRM\/Company\/([0-9]+)/',$g,$reqs))
					DB::Execute('UPDATE utils_attachment_link SET func=%s,args=%s WHERE id=%d',array(serialize(array('CRM_ContactsCommon','search_format_company')),serialize(array($reqs[1])),$i));
			}
		}
		
		if (ModuleManager::is_installed('Premium_ListManager')>=0) {
			$task_ats = DB::GetAssoc('SELECT ual.id,ual.local FROM utils_attachment_link ual WHERE ual.local LIKE \'Premium/ListManager/%\'');

			foreach($task_ats as $i=>$g) {
				if(preg_match('/Premium\/ListManager\/([0-9]+)/',$g,$reqs))
					DB::Execute('UPDATE utils_attachment_link SET func=%s,args=%s WHERE id=%d',array(serialize(array('Premium_ListManagerCommon','search_format')),serialize(array($reqs[1])),$i));
			}
		}
		
		if (ModuleManager::is_installed('Premium_Projects')>=0) {
			$task_ats = DB::GetAssoc('SELECT ual.id,ual.local FROM utils_attachment_link ual WHERE ual.local LIKE \'Premium/Projects/%\'');

			foreach($task_ats as $i=>$g) {
				if(preg_match('/Premium\/Projects\/Tickets([0-9]+)/',$g,$reqs))
					DB::Execute('UPDATE utils_attachment_link SET func=%s,args=%s WHERE id=%d',array(serialize(array('Premium_Projects_TicketsCommon','search_format')),serialize(array($reqs[1])),$i));
				elseif(preg_match('/Premium\/Projects\/([0-9]+)/',$g,$reqs))
					DB::Execute('UPDATE utils_attachment_link SET func=%s,args=%s WHERE id=%d',array(serialize(array('Premium_ProjectsCommon','search_format')),serialize(array($reqs[1])),$i));
			}
		}
		
		if (ModuleManager::is_installed('Premium_Warehouse')>=0) {
			$task_ats = DB::GetAssoc('SELECT ual.id,ual.local FROM utils_attachment_link ual WHERE ual.local LIKE \'Premium/Warehouse/%\'');

			foreach($task_ats as $i=>$g) {
				if(preg_match('/Premium\/Warehouse\/Items\/Orders\/([0-9]+)/',$g,$reqs))
					DB::Execute('UPDATE utils_attachment_link SET func=%s,args=%s WHERE id=%d',array(serialize(array('Premium_Warehouse_Items_OrdersCommon','search_format')),serialize(array($reqs[1])),$i));
				elseif(preg_match('/Premium\/Warehouse\/Items\/([0-9]+)/',$g,$reqs))
					DB::Execute('UPDATE utils_attachment_link SET func=%s,args=%s WHERE id=%d',array(serialize(array('Premium_Warehouse_ItemsCommon','search_format')),serialize(array($reqs[1])),$i));
				elseif(preg_match('/Premium\/Warehouse\/([0-9]+)/',$g,$reqs))
					DB::Execute('UPDATE utils_attachment_link SET func=%s,args=%s WHERE id=%d',array(serialize(array('Premium_WarehouseCommon','search_format')),serialize(array($reqs[1])),$i));
			}
		}

		if (ModuleManager::is_installed('CRM_Tasks')>=0) {
			$task_ats = DB::GetAssoc('SELECT ual.id,ual.local FROM utils_attachment_link ual WHERE ual.local LIKE \'CRM/Tasks/%\'');

			foreach($task_ats as $i=>$g) {
				if(preg_match('/CRM\/Tasks\/([0-9]+)/',$g,$reqs))
					DB::Execute('UPDATE utils_attachment_link SET func=%s,args=%s WHERE id=%d',array(serialize(array('CRM_TasksCommon','search_format')),serialize(array($reqs[1])),$i));
			}
		}
	}

	if (ModuleManager::is_installed('CRM_Calendar')>=0) {
		if (!array_key_exists(strtoupper('deleted'),DB::MetaColumnNames('crm_calendar_event'))) {
			PatchDBAddColumn('crm_calendar_event','deleted','I1 DEFAULT 0');
			DB::CreateIndex('crm_calendar_event__deleted__idx', 'crm_calendar_event', 'deleted');
		}
	}
	
	if (ModuleManager::is_installed('CRM_PhoneCall')>=0 || 
		ModuleManager::is_installed('CRM_Tasks')>=0 || 
		ModuleManager::is_installed('CRM_Calendar_Event')>=0) {
		ob_start();
		ModuleManager::install('CRM_Common');	
		ob_end_clean();
	}
	
	$tabs = DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties');

	$changes = array(	'Permissions'=>'CRM/Access',
					'Ticket_Status'=>'CRM/Status',
					'Priorities'=>'CRM/Priority');
	foreach ($tabs as $t) {
		$fields = DB::Execute('SELECT * FROM '.$t.'_field WHERE type=\'select\'');
		while ($f=$fields->FetchRow()) {
			$p = explode('::',$f['param']);
			if (isset($p[0]) && isset($p[1]) && $p[0]=='__COMMON__' && isset($changes[$p[1]])) {
				DB::Execute('UPDATE '.$t.'_field SET param=%s WHERE field=%s', array('__COMMON__::'.$changes[$p[1]], $f['field']));
			}
		}
	}

	Utils_CommonDataCommon::remove('Permissions');
	Utils_CommonDataCommon::remove('Ticket_Status');
	Utils_CommonDataCommon::remove('Priorities');
}

function update_from_1_0_0rc6_to_1_0_0() {
	if (ModuleManager::is_installed('Utils_Watchdog')>=0) {
		PatchDBAddColumn('utils_watchdog_event','event_time','T');
		DB::CreateIndex('utils_watchdog_event__cat_int__idx', 'utils_watchdog_event', array('category_id','internal_id'));
		DB::CreateIndex('utils_watchdog_subscription__cat_int__idx', 'utils_watchdog_subscription', array('category_id','internal_id'));
		DB::CreateIndex('utils_watchdog_subscription__user__idx', 'utils_watchdog_subscription', 'user_id');
	}

    //fix RB common data fields
    if (ModuleManager::is_installed('Utils/RecordBrowser')>=0) {
		if(ModuleManager::is_installed('CRM/PhoneCall')>=0) {
	    	    DB::Execute('UPDATE phonecall_data_1 SET f_company_name=-1 WHERE f_company_name=%s', array('_no_company'));
		    $rs = Utils_RecordBrowserCommon::get_records('phonecall');
		    foreach ($rs as $r) {
			$p = explode('__',$r['phone']);
		        if (isset($p[1])) {
		    	    $p = $p[1];
			    Utils_RecordBrowserCommon::update_record('phonecall', $r['id'], array('phone'=>$p));
		        }
		    }
		}
	
		$tabs = DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties');
		foreach ($tabs as $t) {
		    $fields = DB::GetAssoc('SELECT field, param FROM '.$t.'_field WHERE type=%s', array('select'));
		    foreach ($fields as $f=>$p) {
			$fk = strtolower(str_replace(' ','_',$f));
			$param = explode('::',$p);
			if ($param[0]=='__COMMON__') {
				unset($param[0]);
				$param = '1__'.implode('::', $param);
				DB::Execute('UPDATE '.$t.'_field SET param=%s, type=%s WHERE field=%s', array($param, 'commondata', $f));
				PatchDBRenameColumn($t.'_data_1', 'f_'.$fk, 'f2_'.$fk, 'C(128)');
				PatchDBRenameColumn($t.'_data_1', 'f2_'.$fk, 'f_'.$fk, 'C(128)');
			} else {
				PatchDBRenameColumn($t.'_data_1', 'f_'.$fk, 'f2_'.$fk, 'I4');
				PatchDBRenameColumn($t.'_data_1', 'f2_'.$fk, 'f_'.$fk, 'I4');
			}
		    }
		}

		$tabs = DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties');
		foreach ($tabs as $t) {
			PatchDBAddColumn($t.'_callback', 'callback', 'C(255)');
			$ret = DB::Execute('SELECT * FROM '.$t.'_callback');
			while ($row = $ret->FetchRow())
				DB::Execute('UPDATE '.$t.'_callback SET callback=%s WHERE field=%s AND module=%s AND func=%s AND freezed=%d', array($row['module'].'::'.$row['func'], $row['field'], $row['module'], $row['func'], $row['freezed']));
			PatchDBDropColumn($t.'_callback', 'module');
			PatchDBDropColumn($t.'_callback', 'func');
		}
    }

    //calendar custom fields
    $tables_db = DB::MetaTables();
    if (ModuleManager::is_installed('CRM_Calendar_Event')>=0) {
	if(!in_array('crm_calendar_event_custom_fields',$tables_db))
	    DB::CreateTable('crm_calendar_event_custom_fields',
		'id I AUTO KEY, '.
		'field C(64), '.
		'callback C(128)',
		array('constraints'=>''));
    }
    
    //company add mail and tax id
    if (ModuleManager::is_installed('CRM_Contacts')>=0) {
	Utils_RecordBrowserCommon::new_record_field('company','Email','text', false, false, '128', '', false, false, 7);
	Utils_RecordBrowserCommon::set_QFfield_callback('company','Email',array('CRM_ContactsCommon', 'QFfield_email'));
	Utils_RecordBrowserCommon::set_display_callback('company','Email',array('CRM_ContactsCommon', 'display_email'));
	Utils_RecordBrowserCommon::new_record_field('company','Tax ID','text', false, false, '64', '', false, false);
    }
    
    //logo
    if (ModuleManager::is_installed('Base_MainModuleIndicator')>=0) {
	ModuleManager::create_data_dir('Base_MainModuleIndicator');
	Variable::set('logo_file','');
    }
    
    //messenger
    if (ModuleManager::is_installed('Utils_Messenger')>=0) {
	PatchDBAddColumn('utils_messenger_users','done_on','T');
	PatchDBAddColumn('utils_messenger_users','follow','I1 DEFAULT 0');
    }
    
    //currencies
    if (ModuleManager::is_installed('Utils_CurrencyField')>=0 && !in_array('utils_currency',$tables_db)) {
	DB::CreateTable('utils_currency',
			'id I AUTO KEY,'.
			'symbol C(16),'.
			'code C(8),'.
			'decimal_sign C(2),'.
			'thousand_sign C(2),'.
			'decimals I1,'.
			'active I1,'.
			'pos_before I1',
			array('constraints'=>''));
	DB::Execute('INSERT INTO utils_currency (id, symbol, code, decimal_sign, thousand_sign, decimals, pos_before, active) VALUES (%d, %s, %s, %s, %s, %d, %d, %d)',
			array(1, '$', 'USD', '.', ',', 2, 1, 1));
    }

    //crm default filter
    if (ModuleManager::is_installed('CRM_Filters')>=0 && !in_array('crm_filters_default',$tables_db)) {
	DB::CreateTable('crm_filters_default','
			user_login_id I4 NOTNULL,
			filter C(16)',
			array('constraints'=>', FOREIGN KEY (user_login_id) REFERENCES user_login(id)'));
		
		if(DATABASE_DRIVER=='mysqlt' || DATABASE_DRIVER=='mysqli') {
			DB::Execute('alter table crm_filters_group drop key `name`');
			DB::Execute('alter table crm_filters_group ADD UNIQUE(`name`,`user_login_id`)');
    	}
    }

    //mail client
    if (ModuleManager::is_installed('Apps_MailClient')>=0) {
	if(!in_array('apps_mailclient_filters',$tables_db))
		DB::CreateTable('apps_mailclient_filters','
			id I4 AUTO KEY,
			account_id I4 NOTNULL,
			name C(64),
			match_method I1 DEFAULT 0',
			array('constraints'=>', FOREIGN KEY (account_id) REFERENCES apps_mailclient_accounts(ID)'));

	if(!in_array('apps_mailclient_filter_rules',$tables_db))
		DB::CreateTable('apps_mailclient_filter_rules','
			id I4 AUTO KEY,
			filter_id I4 NOTNULL,
			header C(64),
			rule I1 DEFAULT 0,
			value C(128)',
			array('constraints'=>', FOREIGN KEY (filter_id) REFERENCES apps_mailclient_filters(ID)'));

	if(!in_array('apps_mailclient_filter_actions',$tables_db))
		DB::CreateTable('apps_mailclient_filter_actions','
			id I4 AUTO KEY,
			filter_id I4 NOTNULL,
			action I1 DEFAULT 0,
			value C(128)',
			array('constraints'=>', FOREIGN KEY (filter_id) REFERENCES apps_mailclient_filters(ID)'));
    }    
}

function update_from_1_0_0_to_1_0_1() {
	if (ModuleManager::is_installed('Utils/RecordBrowser')>=0) {
		$tabs = DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties');
		foreach ($tabs as $t) {
			DB::Execute('UPDATE '.$t.'_field SET type=%s WHERE type=%s', array('float', 'integer'));
			DB::Execute('UPDATE '.$t.'_field SET style=%s WHERE style=%s', array('number', 'integer'));
		}
	}

	if (ModuleManager::is_installed('Data_Countries')>=0) {

		$calling_codes = array(
			'US'=>'+1',
			'CA'=>'+1',
			'BS'=>'+1242',
			'BB'=>'+1246',
			'AI'=>'+1264',
			'AG'=>'+1268',
			'VG'=>'+1284',
			'VI'=>'+1340',
			'KY'=>'+1345',
			'BM'=>'+1441',
			'GD'=>'+1473',
			'TC'=>'+1649',
			'MS'=>'+1664',
			'MP'=>'+1670',
			'GU'=>'+1671',
			'AS'=>'+1684',
			'LC'=>'+1758',
			'DM'=>'+1767',
			'VC'=>'+1784',
			'PR'=>'+1787',
			'DO'=>'+1809',
			'DO'=>'+1829',
			'DO'=>'+1849',
			'TT'=>'+1868',
			'KN'=>'+1869',
			'JM'=>'+1876',
			'PR'=>'+1939',
			'EG'=>'+20',
			'MA'=>'+212',
			'EH'=>'+212',
			'DZ'=>'+213',
			'TN'=>'+216',
			'LY'=>'+218',
			'GM'=>'+220',
			'SN'=>'+221',
			'MR'=>'+222',
			'ML'=>'+223',
			'GN'=>'+224',
			'CI'=>'+225',
			'BF'=>'+226',
			'NE'=>'+227',
			'TG'=>'+228',
			'BJ'=>'+229',
			'MU'=>'+230',
			'LR'=>'+231',
			'SL'=>'+232',
			'GH'=>'+233',
			'NG'=>'+234',
			'TD'=>'+235',
			'CF'=>'+236',
			'CM'=>'+237',
			'CV'=>'+238',
			'ST'=>'+239',
			'GQ'=>'+240',
			'GA'=>'+241',
			'CG'=>'+242',
			'CD'=>'+243',
			'AO'=>'+244',
			'GW'=>'+245',
			'IO'=>'+246',
			'AC'=>'+247',
			'SC'=>'+248',
			'SD'=>'+249',
			'RW'=>'+250',
			'ET'=>'+251',
			'SO'=>'+252',
			'QS'=>'+252',
			'DJ'=>'+253',
			'KE'=>'+254',
			'TZ'=>'+255',
			'UG'=>'+256',
			'BI'=>'+257',
			'MZ'=>'+258',
			'ZM'=>'+260',
			'MG'=>'+261',
			'RE'=>'+262',
			'YT'=>'+262',
			'ZW'=>'+263',
			'NA'=>'+264',
			'MW'=>'+265',
			'LS'=>'+266',
			'BW'=>'+267',
			'SZ'=>'+268',
			'KM'=>'+269',
			'ZA'=>'+27',
			'SH'=>'+290',
			'TA'=>'+290',
			'ER'=>'+291',
			'AW'=>'+297',
			'FO'=>'+298',
			'GL'=>'+299',
			'GR'=>'+30',
			'NL'=>'+31',
			'BE'=>'+32',
			'FR'=>'+33',
			'ES'=>'+34',
			'GI'=>'+350',
			'PT'=>'+351',
			'LU'=>'+352',
			'IE'=>'+353',
			'IS'=>'+354',
			'AL'=>'+355',
			'MT'=>'+356',
			'CY'=>'+357',
			'FI'=>'+358',
			'AX'=>'+358',
			'BG'=>'+359',
			'HU'=>'+36',
			'LT'=>'+370',
			'LV'=>'+371',
			'EE'=>'+372',
			'MD'=>'+373',
			'AM'=>'+374',
			'QN'=>'+374',
			'BY'=>'+375',
			'AD'=>'+376',
			'MC'=>'+377',
			'SM'=>'+378',
			'VA'=>'+379',
			'UA'=>'+380',
			'RS'=>'+381',
			'ME'=>'+382',
			'HR'=>'+385',
			'SI'=>'+386',
			'BA'=>'+387',
			'EU'=>'+388',
			'MK'=>'+389',
			'IT'=>'+39',
			'VA'=>'+39',
			'RO'=>'+40',
			'CH'=>'+41',
			'CZ'=>'+420',
			'SK'=>'+421',
			'LI'=>'+423',
			'AT'=>'+43',
			'GB'=>'+44',
			'GG'=>'+44',
			'IM'=>'+44',
			'JE'=>'+44',
			'DK'=>'+45',
			'SE'=>'+46',
			'NO'=>'+47',
			'SJ'=>'+47',
			'PL'=>'+48',
			'DE'=>'+49',
			'FK'=>'+500',
			'BZ'=>'+501',
			'GT'=>'+502',
			'SV'=>'+503',
			'HN'=>'+504',
			'NI'=>'+505',
			'CR'=>'+506',
			'PA'=>'+507',
			'PM'=>'+508',
			'HT'=>'+509',
			'PE'=>'+51',
			'MX'=>'+52',
			'CU'=>'+53',
			'AR'=>'+54',
			'BR'=>'+55',
			'CL'=>'+56',
			'CO'=>'+57',
			'VE'=>'+58',
			'GP'=>'+590',
			'BL'=>'+590',
			'MF'=>'+590',
			'BO'=>'+591',
			'GY'=>'+592',
			'EC'=>'+593',
			'GF'=>'+594',
			'PY'=>'+595',
			'MQ'=>'+596',
			'SR'=>'+597',
			'UY'=>'+598',
			'AN'=>'+599',
			'MY'=>'+60',
			'AU'=>'+61',
			'CX'=>'+61',
			'CC'=>'+61',
			'ID'=>'+62',
			'PH'=>'+63',
			'NZ'=>'+64',
			'SG'=>'+65',
			'TH'=>'+66',
			'TL'=>'+670',
			'NF'=>'+672',
			'AQ'=>'+672',
			'BN'=>'+673',
			'NR'=>'+674',
			'PG'=>'+675',
			'TO'=>'+676',
			'SB'=>'+677',
			'VU'=>'+678',
			'FJ'=>'+679',
			'PW'=>'+680',
			'WF'=>'+681',
			'CK'=>'+682',
			'NU'=>'+683',
			'WS'=>'+685',
			'KI'=>'+686',
			'NC'=>'+687',
			'TV'=>'+688',
			'PF'=>'+689',
			'TK'=>'+690',
			'FM'=>'+691',
			'MH'=>'+692',
			'RU'=>'+7',
			'KZ'=>'+7',
			'XT'=>'+800',
			'XS'=>'+808',
			'JP'=>'+81',
			'KR'=>'+82',
			'VN'=>'+84',
			'KP'=>'+850',
			'HK'=>'+852',
			'MO'=>'+853',
			'KH'=>'+855',
			'LA'=>'+856',
			'CN'=>'+86',
			'XN'=>'+870',
			'PN'=>'+872',
			'XP'=>'+878',
			'BD'=>'+880',
			'XG'=>'+881',
			'XV'=>'+882',
			'XL'=>'+883',
			'TW'=>'+886',
			'XD'=>'+888',
			'TR'=>'+90',
			'QY'=>'+90',
			'IN'=>'+91',
			'PK'=>'+92',
			'AF'=>'+93',
			'LK'=>'+94',
			'MM'=>'+95',
			'MV'=>'+960',
			'LB'=>'+961',
			'JO'=>'+962',
			'SY'=>'+963',
			'IQ'=>'+964',
			'KW'=>'+965',
			'SA'=>'+966',
			'YE'=>'+967',
			'OM'=>'+968',
			'PS'=>'+970',
			'AE'=>'+971',
			'IL'=>'+972',
			'PS'=>'+972',
			'BH'=>'+973',
			'QA'=>'+974',
			'BT'=>'+975',
			'MN'=>'+976',
			'NP'=>'+977',
			'XR'=>'+979',
			'IR'=>'+98',
			'XC'=>'+991',
			'TJ'=>'+992',
			'TM'=>'+993',
			'AZ'=>'+994',
			'QN'=>'+994',
			'GE'=>'+995',
			'KG'=>'+996',
			'UZ'=>'+998');
		Utils_CommonDataCommon::new_array('Calling_Codes',$calling_codes);
	}
	
	if(DATABASE_DRIVER=='mysqlt' || DATABASE_DRIVER=='mysqli') {
		DB::Execute('SET FOREIGN_KEY_CHECKS=0');

		$tables = DB::MetaTables();
		foreach($tables as $t) {
		        $ret = DB::GetRow('SHOW CREATE TABLE '.$t);
			if(preg_match('/utf8_unicode_ci/',$ret[1])) continue;
			$cols = explode("\n",$ret[1]);
		        foreach($cols as $c) {
				if(preg_match('/(varchar|text)/',$c) && !preg_match('/latin/',$c)) {
					if($t=='aro' || $t=='axo') $c = str_replace('240','160',$c);
					DB::Execute('ALTER TABLE `'.$t.'` MODIFY '.rtrim($c,',').' COLLATE utf8_unicode_ci');
				}
			}
			$mcols = DB::MetaColumns($t);
			foreach($mcols as $c) {
				if(isset($mcols['ID']) && ($c->type=="text" || $c->type=="varchar")) {
					DB::Execute('SET NAMES latin1');
					$data = DB::GetAssoc('SELECT id,'.$c->name.' FROM '.$t);
					DB::Execute('SET NAMES utf8');
					foreach($data as $id=>$val) {
						DB::Execute('UPDATE '.$t.' SET '.$c->name.'=%s WHERE id=%s',array($val,$id));
					}
				}
			}
		}
		DB::Execute('DELETE FROM history');
		DB::Execute('DELETE FROM session_client');
		DB::Execute('DELETE FROM session');

		if (ModuleManager::is_installed('Base_HomePage')>=0)
			DB::Execute('DELETE FROM home_page');
	}
}

function update_from_1_0_1_to_1_0_2() {
	if (ModuleManager::is_installed('CRM_MailClient')>=0) {

		$tables = DB::MetaTables();
		
		if(!in_array('crm_mailclient_addons',$tables)) {
			DB::CreateTable('crm_mailclient_addons','
				tab C(64) KEY NOTNULL,
				format_callback C(128) NOTNULL,
				crits C(256)');
		}
		if(!in_array('crm_mailclient_rb_mails',$tables)) {
			DB::CreateTable('crm_mailclient_rb_mails','
				mail_id I4 NOTNULL,
				rec_id I4 NOTNULL,
				tab C(64) NOTNULL',
				array('constraints'=>', FOREIGN KEY (mail_id) REFERENCES crm_mailclient_mails(ID)'));
		}
		
		DB::Execute('DELETE FROM crm_mailclient_addons');

		$tab = 'task';
		$format_callback = array('CRM_TasksCommon','display_title_with_mark');
		$crits = array('!status'=>array(2,3));
		DB::Execute('INSERT INTO crm_mailclient_addons(tab,format_callback,crits) VALUES (%s,%s,%s)',array($tab,serialize($format_callback),serialize($crits)));
		Utils_RecordBrowserCommon::new_addon($tab, 'CRM/MailClient', 'rb_addon', 'Mails');

		$r = DB::dict()->AlterColumnSQL('crm_mailclient_mails','
			id I4 AUTO KEY NOTNULL,
			delivered_on T NOTNULL,
			from_contact_id I4 DEFAULT NULL,
			to_contact_id I4 DEFAULT NULL,
			deleted I1 DEFAULT 0,
			sticky I1 DEFAULT 0,
			headers X,
			subject C(255),
			body X,
			body_type C(16),
			body_ctype C(32)','',
			array('constraints'=>', FOREIGN KEY (from_contact_id) REFERENCES contact_data_1(ID), FOREIGN KEY (to_contact_id) REFERENCES contact_data_1(ID)'));
		foreach($r as $rr) {
			DB::Execute($rr);
		}

		$tab = 'contact';
		$format_callback = null;
		$crits = null;
		DB::Execute('INSERT INTO crm_mailclient_addons(tab,format_callback,crits) VALUES (%s,%s,%s)',array($tab,serialize($format_callback),serialize($crits)));
	}
	
}

function update_from_1_0_2_to_1_0_3() {
	// Check if module is installed
	if (ModuleManager::is_installed('CRM_Calendar')>=0) {
		@DB::DropTable('crm_calendar_custom_events_handlers');
		DB::CreateTable('crm_calendar_custom_events_handlers',
				'id I4 AUTO KEY,'.
				'group_name C(64),'.
				'get_callback C(128),'.
				'get_all_callback C(128),'.
				'delete_callback C(128),'.
				'update_callback C(128)',
				array('constraints'=>''));
	}
}

function update_from_1_0_3_to_1_0_4() {
	// Check if module is installed
	if (ModuleManager::is_installed('CRM_Contacts')>=0) {
		DB::Execute("UPDATE company_field SET required=0 WHERE field='City'");
		Utils_RecordBrowserCommon::set_display_callback('contact','Home Address 1',array('CRM_ContactsCommon','home_maplink'));
		Utils_RecordBrowserCommon::set_display_callback('contact','Home Address 2',array('CRM_ContactsCommon','home_maplink'));
		Utils_RecordBrowserCommon::set_display_callback('contact','Home City',array('CRM_ContactsCommon','home_maplink'));
	}
	
	if (ModuleManager::is_installed('Utils_RecordBrowser')>=0) {
		DB::Execute('ALTER TABLE recordbrowser_addon drop primary key');
		DB::Execute('ALTER TABLE recordbrowser_addon add primary key (tab,module,func)');
		
		$tables = DB::MetaTables();
		if(!in_array('recordbrowser_clipboard_pattern',$tables))
			DB::CreateTable('recordbrowser_clipboard_pattern', 'tab C(64) KEY, pattern X, enabled I4');
			
		$tabs = DB::GetCol('SELECT tab FROM recordbrowser_table_properties');
		foreach($tabs as $tab)
			PatchDBAlterColumn($tab.'_edit_history_data','old_value','X');
	}
}

function update_from_1_0_4_to_1_0_5() {
	if (ModuleManager::is_installed('Base_Backup')>=0) {
		DB::Execute('DELETE FROM modules WHERE name=%s',array('Base_Backup'));
		@recursive_rmdir(DATA_DIR.'/Base_Backup');
	}
	@recursive_rmdir(DATA_DIR.'/backup');

	if (ModuleManager::is_installed('CRM_Contacts')>=0) {
		Utils_RecordBrowserCommon::set_clipboard_pattern('contact', "%{{first_name} {last_name}<BR>}\n%{{title}<BR>}\n%{{company_name}<BR>}\n%{{address_1}<BR>}\n%{{address_2}<BR>}\n%{%{{city} }%{{zone} }{postal_code}<BR>}\n%{{country}<BR>}\n%{tel. {work_phone}<BR>}\n%{{email}<BR>}");
		Utils_RecordBrowserCommon::set_clipboard_pattern('company', "%{{company_name}<BR>}\n%{{address_1}<BR>}\n%{{address_2}<BR>}\n%{%{{city} }%{{zone} }{postal_code}<BR>}\n%{{country}<BR>}\n%{tel. {phone}<BR>}\n%{fax. {fax}<BR>}\n%{{web_address}<BR>}");
	}
	
	if (ModuleManager::is_installed('Utils_RecordBrowser')>=0) {
		$tables = DB::MetaTables();
		if(!in_array('recordbrowser_extended_search',$tables))
			DB::CreateTable('recordbrowser_extended_search', 'tab C(64), icon C(32), label C(64), callback C(128)', array('constraints'=>', PRIMARY KEY(tab, label)'));
	}
}

function update_from_1_0_6_to_1_0_7() {
	if (ModuleManager::is_installed('Utils_RecordBrowser')!=-1) {
		$tables = DB::MetaTables();
		if(!in_array('recordbrowser_browse_mode_definitions',$tables))
			DB::CreateTable('recordbrowser_browse_mode_definitions',
				'tab C(64),'.
				'module C(128),'.
				'func C(128)',
				array('constraints'=>', PRIMARY KEY(tab, module, func)'));
	}

	if (ModuleManager::is_installed('CRM_Contacts')!=-1) {
		@Utils_RecordBrowserCommon::register_datatype('crm_company_contact', 'CRM_ContactsCommon', 'crm_company_contact_datatype');
	}

	if (ModuleManager::is_installed('CRM_PhoneCall')>=0) {
		Utils_RecordBrowserCommon::new_addon('phonecall', 'CRM/PhoneCall', 'messanger_addon', 'Alerts');
		
		PatchDBRenameColumn('phonecall_data_1','f_other_contact','f_other_customer','I1');
		DB::Execute('UPDATE phonecall_field SET field=%s WHERE field=%s', array('Other Customer','Other Contact'));
		PatchDBRenameColumn('phonecall_data_1','f_other_contact_name','f_other_customer_name','C(64)');
		DB::Execute('UPDATE phonecall_field SET field=%s WHERE field=%s', array('Other Customer Name','Other Contact Name'));
		PatchDBRenameColumn('phonecall_data_1','f_contact','f_customer','C(64)');
		DB::Execute('UPDATE phonecall_field SET field=%s, type=%s, param=%s WHERE field=%s', array('Customer','text',64,'Contact'));
		Utils_RecordBrowserCommon::delete_record_field('phonecall','Company Name');
		Utils_RecordBrowserCommon::set_QFfield_callback('phonecall', 'Customer', array('CRM_ContactsCommon', 'QFfield_company_contact'));
		Utils_RecordBrowserCommon::set_display_callback('phonecall', 'Customer', array('CRM_ContactsCommon', 'display_company_contact'));
		$ret = DB::Execute('SELECT * FROM phonecall_data_1');
		While ($row=$ret->FetchRow()) {
			if (!$row['f_customer']) continue;
			if (isset($row['f_customer'][1]) && $row['f_customer'][1]==':') continue;
			DB::Execute('UPDATE phonecall_data_1 SET f_customer=%s WHERE id=%d', array('P:'.$row['f_customer'], $row['id']));
		}

		DB::Execute('UPDATE phonecall_callback SET field=%s WHERE field=%s', array('Other Customer', 'Other Contact'));
	}
	
	if (ModuleManager::is_installed('CRM_Tasks')!=-1) {
		Utils_RecordBrowserCommon::set_QFfield_callback('task', 'Customers', array('CRM_ContactsCommon', 'QFfield_company_contact'));
		Utils_RecordBrowserCommon::set_display_callback('task', 'Customers', array('CRM_ContactsCommon', 'display_company_contact'));
		$ret = DB::Execute('SELECT * FROM task_data_1');
		while ($row=$ret->FetchRow()) {
			$conts = explode('__',trim($row['f_customers'],'_'));
			if (isset($conts[0]{1}) && $conts[0]{1}==':') continue;
			$conts = '__P:'.implode('__P:', $conts).'__';
			DB::Execute('UPDATE task_data_1 SET f_customers=%s WHERE id=%d', array($conts, $row['id']));
		}

		Utils_RecordBrowserCommon::new_addon('task', 'CRM/Tasks', 'messanger_addon', 'Alerts');
	}

}

function update_from_1_0_8_to_1_0_8b() {
	if (ModuleManager::is_installed('Apps_MailClient')!=-1 ||
		ModuleManager::is_installed('Premium_Projects')!=-1) {
		
		ob_start();
		ModuleManager::install('Utils_RecordBrowser_RecordPicker');
		ob_end_clean();
	}
}

function update_from_1_0_8b_to_1_0_9() {
    if (ModuleManager::is_installed('Premium_Projects_Tickets')>=0) {
        Utils_RecordBrowserCommon::new_record_field('premium_tickets',array('name'=>'Ticket Owner', 'type'=>'crm_contact', 'param'=>array('field_type'=>'select', 'crits'=>array('Premium_Projects_TicketsCommon','users_crits'),'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'display_callback'=>array('Premium_Projects_TicketsCommon','display_assigned_contacts'), 'required'=>true, 'extra'=>false, 'visible'=>true, 'position'=>'Project Name'));

        $current = Utils_CommonDataCommon::get_array('Premium_Ticket_Status');
        if (count($current)==5) {
	        Utils_CommonDataCommon::new_array('Premium_Ticket_Status',array('New','Open','In Progress','On Hold','Resolved','Awaiting Feedback','Closed'), true,true);
    	    DB::Execute('UPDATE premium_tickets_data_1 SET f_status=f_status+2 WHERE f_status>=2');
        }
    
        $ret = DB::Execute('SELECT * FROM premium_tickets_data_1');

        while ($row=$ret->FetchRow()) {
	        if (!$row['f_ticket_owner']) {
		        $c_id = CRM_ContactsCommon::get_contact_by_user_id($row['created_by']);
    		    DB::Execute('UPDATE premium_tickets_data_1 SET f_ticket_owner=%d WHERE id=%d', array($c_id['id'], $row['id']));
        	}
        }
    }
    
    if (ModuleManager::is_installed('CRM_Calendar')>=0) {

        DB::DropTable('crm_calendar_custom_events_handlers');

        DB::CreateTable('crm_calendar_custom_events_handlers',
				'id I4 AUTO KEY,'.
				'group_name C(64),'.
				'handler_callback C(128)',
				array('constraints'=>''));

    }

    if (ModuleManager::is_installed('CRM_Common')>=0) {
        $current = Utils_CommonDataCommon::get_array('CRM/Status');
        if (count($current)!=5) {
            Utils_CommonDataCommon::new_array('CRM/Status',array('Open','In Progress','On Hold','Closed','Canceled'), true,true);

            if (ModuleManager::is_installed('CRM_Tasks')>=0) {
            	DB::Execute('UPDATE task_data_1 SET f_status=f_status+1 WHERE f_status>=2');
            }

            if (ModuleManager::is_installed('CRM_Meeting')>=0) {
            	DB::Execute('UPDATE crm_meeting_data_1 SET f_status=f_status+1 WHERE f_status>=2');
            }

            if (ModuleManager::is_installed('CRM_PhoneCall')>=0) {
            	DB::Execute('UPDATE phonecall_data_1 SET f_status=f_status+1 WHERE f_status>=2');
            }
        }
    }

    if (ModuleManager::is_installed('Utils_RecordBrowser')>=0) {

        $tabs = DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties');
        foreach ($tabs as $t) {
        	@DB::Execute('ALTER TABLE '.$t.'_edit_history ADD CONSTRAINT '.$t.'_id_data_fkey FOREIGN KEY ('.$t.'_id) REFERENCES '.$t.'_data_1 (id)');
        }

    }
    
    if (ModuleManager::is_installed('CRM_Calendar')>=0) {

        $ret = DB::Execute('SELECT * FROM utils_attachment_link WHERE local LIKE '.DB::Concat(DB::qstr('CRM/Calendar/Event/'),DB::qstr('%')));

        while ($row = $ret->FetchRow()) {
        	$func = serialize(array('CRM_MeetingCommon','search_format'));
        	$args = serialize(array(str_replace('CRM/Calendar/Event/','',$row['local'])));
        	DB::Execute('UPDATE utils_attachment_link SET func=%s, args=%s WHERE id=%d', array($func, $args, $row['id']));
        }
    }
    
    DB::Execute('DELETE FROM modules WHERE name="CRM_Calendar_Reports"');
    DB::Execute('DELETE FROM modules WHERE name="CRM_Import"');

    if (ModuleManager::is_installed('Utils_RecordBrowser')>=0) {
	    PatchDBAddColumn('recordbrowser_table_properties','description_callback','C(128)');
    }

    if (ModuleManager::is_installed('CRM_Contacts')>=0) {
	    DB::Execute('UPDATE recordbrowser_table_properties SET description_callback=\'CRM_ContactsCommon::contact_format_default\' WHERE tab=\'contact\'');
    	DB::Execute('UPDATE recordbrowser_table_properties SET description_callback=\'CRM_ContactsCommon::company_format_default\' WHERE tab=\'company\'');
    }

    if (ModuleManager::is_installed('CRM_Tasks')>=0 || ModuleManager::is_installed('CRM_PhoneCall')>=0 || ModuleManager::is_installed('CRM_Meeting')>=0 || ModuleManager::is_installed('Apps_MailClient')>=0) {
        if (ModuleManager::is_installed('CRM_Roundcube')==-1) {
            ob_start();
            ModuleManager::install('CRM_Roundcube');    
    		ob_end_clean();
        }

        if(ModuleManager::is_installed('CRM_Tasks')>=0)
            CRM_RoundcubeCommon::new_addon('task');
        if(ModuleManager::is_installed('CRM_PhoneCall')>=0)
            CRM_RoundcubeCommon::new_addon('phonecall');
        if(ModuleManager::is_installed('CRM_Meeting')>=0)
            CRM_RoundcubeCommon::new_addon('crm_meeting');
    }

    if (ModuleManager::is_installed('CRM_Calendar')>=0) {
        ob_start();
        ModuleManager::install('Utils_LeightboxPrompt');
        ModuleManager::install('CRM_Meeting',0);
		ob_end_clean();

        $fields = @DB::GetAssoc('SELECT field, callback FROM crm_calendar_event_custom_fields');

        if (!$fields) $fields = array();

        $move_fields = array();
        foreach ($fields as $k=>$v) {
        	Utils_RecordBrowserCommon::new_record_field('crm_meeting', 
		        array('name'=>$k, 'type'=>'text', 'required'=>false, 'param'=>'255', 'extra'=>false, 'visible'=>false)
        	);
        	Utils_RecordBrowserCommon::set_QFfield_callback('crm_meeting',$k,explode('::', $v));
        	$move_fields[] = $k;
        }

        $ret = @DB::Execute('SELECT * FROM crm_calendar_event');

        if ($ret)
            while($row = $ret->FetchRow()) {
            	$id = DB::GetOne('SELECT id FROM crm_meeting_data_1 WHERE id=%d', array($row['id']));
            	if (!$id) {
            		DB::Execute('INSERT INTO crm_meeting_data_1 (id, created_on, created_by, active) VALUES (%d, %T, %d, %d)', array($row['id'],$row['created_on'],$row['created_by'],$row['deleted']?0:1));
            		$id = $row['id'];
            	}
            	$new_values = array();
            	$new_values['title'] = $row['title'];
            	$new_values['description'] = $row['description'];
            	$new_values['date'] = date('Y-m-d', $row['starts']);
            	$new_values['time'] = date('1970-01-01 H:i:s', $row['starts']);
            	$new_values['duration'] = $row['timeless']?-1:($row['ends']-$row['starts']);
            	$new_values['permission'] = $row['access'];
            	$new_values['priority'] = $row['priority'];
            	$new_values['color'] = $row['color'];
            	$new_values['status'] = $row['status'];
            	$new_values['recurrence_type'] = $row['recurrence_type'];
            	$new_values['recurrence_end'] = $row['recurrence_end'];
            	$new_values['recurrence_hash'] = $row['recurrence_hash'];
            	$emps = DB::GetCol('SELECT contact FROM crm_calendar_event_group_emp WHERE id=%d',array($id));
            	$cus = DB::GetCol('SELECT contact FROM crm_calendar_event_group_cus WHERE id=%d',array($id));
            	foreach ($cus as $k=>$v) $cus[$k] = 'P:'.$v;
            	$new_values['employees'] = $emps;
            	$new_values['customers'] = $cus;
            	foreach ($move_fields as $v) $new_values[$v] = $row[$v];
            	Utils_RecordBrowserCommon::update_record('crm_meeting', $id, $new_values);
            }
    }
    
    
    if (ModuleManager::is_installed('CRM_Meeting')>=0) {
        Utils_RecordBrowserCommon::new_addon('crm_meeting', 'CRM/Meeting', 'messanger_addon', 'Alerts');

        DB::Execute('UPDATE utils_messenger_message SET parent_module="CRM_Meeting" WHERE parent_module="CRM_Calendar_Event"');
    }

    if (ModuleManager::is_installed('CRM_Contacts')>=0) {
        PatchDBRenameColumn('company_data_1','f_company_name','f_company_name','C(128)');
        DB::Execute('UPDATE company_field SET param=%s WHERE field=%s', array(128,'Company Name'));
    }

    if (ModuleManager::is_installed('Data_Countries')>=0) {

        $ro_wojew = array(
            'AB'=>'Alba',
            'AG'=>'Arges',
            'AR'=>'Arad',
            'B'=>'Bucuresti',
            'BC'=>'Bacau',
            'BH'=>'Bihor',
            'BN'=>'Bistrita-Nasaud',
            'BR'=>'Braila',
            'BT'=>'Botosani',
            'BV'=>'Brasov',
            'BZ'=>'Buzau',
            'CJ'=>'Cluj',
            'CL'=>'Calarasi',
            'CS'=>'Caras-Severin',
            'CT'=>'Constanta',
            'CV'=>'Covasna',
            'DB'=>'Dambovita',
            'DJ'=>'Dolj',
            'GJ'=>'Gorj',
            'GL'=>'Galati',
            'GR'=>'Giurgiu',
            'HD'=>'Hunedoara',
            'HR'=>'Harghita',
            'IF'=>'Ilfov',
            'IL'=>'Ialomita',
            'IS'=>'Iasi',
            'MH'=>'Mehedinti',
            'MM'=>'Maramures',
            'MS'=>'Mures',
            'NT'=>'Neamt',
            'OT'=>'Olt',
            'PH'=>'Prahova',
            'SB'=>'Sibiu',
            'SJ'=>'Salaj',
            'SM'=>'Satu-Mare',
            'SV'=>'Suceava',
            'TL'=>'Tulcea',
            'TM'=>'Timis',
            'TR'=>'Teleorman',
            'VL'=>'Valcea',
            'VN'=>'Vrancea',
            'VS'=>'Vaslui');
		Utils_CommonDataCommon::new_array('Countries/RO',$ro_wojew);

        if(ModuleManager::is_installed('Data_Countries_States_AU')<0) {
		$australian_states = array('ACT'=>"Australian Capital Territory",  
			'NSW'=>"New South Wales",
			'NT'=>"Northern Territory",
			'QLD'=>"Queensland",  
			'SA'=>"South Australia",  
			'TAS'=>"Tasmania",  
			'VIC'=>"Victoria",  
			'WA'=>"Western Australia");  
		    Utils_CommonDataCommon::new_array('Countries/AU',$australian_states);
        }
        DB::Execute('DELETE FROM modules WHERE name="Data_Countries_States_AU"');
    }

}

function update_from_1_0_9_to_1_1_0() {

    if(ModuleManager::is_installed('CRM_Calendar')>=0 && ModuleManager::is_installed('CRM_Meeting')>=0)
	    CRM_CalendarCommon::new_event_handler('Meetings', array('CRM_MeetingCommon', 'crm_calendar_handler'));

    if (ModuleManager::is_installed('Data_Countries')>=0) {
        Utils_CommonDataCommon::set_value('Countries/NA','Namibia');
    }

    if (ModuleManager::is_installed('CRM_PhoneCall')!=-1)
	    Utils_BBCodeCommon::new_bbcode('phone', 'CRM_PhoneCallCommon', 'phone_bbcode');

    if (ModuleManager::is_installed('CRM_Meeting')!=-1) {
	    Utils_BBCodeCommon::new_bbcode('meeting', 'CRM_MeetingCommon', 'meeting_bbcode');
		DB::Execute('DELETE FROM crm_calendar_custom_events_handlers WHERE group_name=%s', array('Meetings'));
		DB::Execute('INSERT INTO crm_calendar_custom_events_handlers (group_name, handler_callback) VALUES (%s, %s)', array('Meetings', 'CRM_MeetingCommon::crm_calendar_handler'));
	}

    if (ModuleManager::is_installed('Utils_RecordBrowser')>=0) {

        $tables_db = DB::MetaTables();
        if(!in_array('recordbrowser_processing_methods',$tables_db)) {

            PatchDBDropColumn('recordbrowser_table_properties', 'data_process_method');

            DB::CreateTable('recordbrowser_processing_methods',
		   	'tab C(64),'.
			'func C(255)',
			array('constraints'=>', PRIMARY KEY(tab, func)'));


            if (ModuleManager::is_installed('CRM_Assets')!=-1)
            	Utils_RecordBrowserCommon::register_processing_callback('crm_assets', array('CRM_AssetsCommon', 'process_request'));

            if (ModuleManager::is_installed('CRM_Contacts')!=-1)
            	Utils_RecordBrowserCommon::register_processing_callback('contact', array('CRM_ContactsCommon', 'submit_contact'));

            if (ModuleManager::is_installed('CRM_Contacts_Photo')!=-1)
            	Utils_RecordBrowserCommon::register_processing_callback('contact', array('CRM_Contacts_PhotoCommon', 'submit_contact'));

            if (ModuleManager::is_installed('CRM_Meeting')!=-1)
        	    Utils_RecordBrowserCommon::register_processing_callback('crm_meeting', array('CRM_MeetingCommon', 'submit_meeting'));

            if (ModuleManager::is_installed('CRM_PhoneCall')!=-1)
            	Utils_RecordBrowserCommon::register_processing_callback('phonecall', array('CRM_PhoneCallCommon', 'submit_phonecall'));

        if (ModuleManager::is_installed('CRM_Roundcube')!=-1)
        	Utils_RecordBrowserCommon::register_processing_callback('rc_accounts', array('CRM_RoundcubeCommon', 'submit_account'));

        if (ModuleManager::is_installed('CRM_Tasks')!=-1)
        	Utils_RecordBrowserCommon::register_processing_callback('task', array('CRM_TasksCommon', 'submit_task'));

if (ModuleManager::is_installed('Custom_CADES_Allergies')!=-1)
   	Utils_RecordBrowserCommon::register_processing_callback('cades_allergies', array('Custom_CADES_AllergiesCommon', 'process_callback'));

if (ModuleManager::is_installed('Custom_CADES_Appointments')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('cades_appointments', array('Custom_CADES_AppointmentsCommon', 'process_callback'));

if (ModuleManager::is_installed('Custom_CADES_Behavior')!=-1) {
	Utils_RecordBrowserCommon::register_processing_callback('cades_behavior_log', array('Custom_CADES_BehaviorCommon', 'process_log_callback'));
	Utils_RecordBrowserCommon::register_processing_callback('cades_behavior', array('Custom_CADES_BehaviorCommon', 'process_callback'));
}

if (ModuleManager::is_installed('Custom_CADES_ContactGroups')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('contact', array('Custom_CADES_ContactGroupsCommon', 'submit_contact'));

if (ModuleManager::is_installed('Custom_CADES_Diet')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('cades_diet', array('Custom_CADES_DietCommon', 'process_callback'));

if (ModuleManager::is_installed('Custom_CADES_Hospitalizations')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('cades_hospitalizations', array('Custom_CADES_HospitalizationsCommon', 'process_callback'));

if (ModuleManager::is_installed('Custom_CADES_Immunizations')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('cades_immunizations', array('Custom_CADES_ImmunizationsCommon', 'process_callback'));

if (ModuleManager::is_installed('Custom_CADES_Insurance')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('cades_insurance', array('Custom_CADES_InsuranceCommon', 'process_callback'));

if (ModuleManager::is_installed('Custom_CADES_Issues')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('cades_issues', array('Custom_CADES_IssuesCommon', 'submit_issue'));

if (ModuleManager::is_installed('Custom_CADES_MedicalTests')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('cades_medicaltests', array('Custom_CADES_MedicalTestsCommon', 'process_callback'));

if (ModuleManager::is_installed('Custom_CADES_Reviews')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('cades_reviews', array('Custom_CADES_ReviewsCommon', 'process_callback'));

if (ModuleManager::is_installed('Custom_CADES_Seizures')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('cades_seizures', array('Custom_CADES_SeizuresCommon', 'process_callback'));

if (ModuleManager::is_installed('Custom_CADES_Services')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('cades_services', array('Custom_CADES_ServicesCommon', 'process_callback'));

if (ModuleManager::is_installed('Custom_CADES_Sleep')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('cades_sleep', array('Custom_CADES_SleepCommon', 'process_callback'));

if (ModuleManager::is_installed('Custom_CADES_Toileting')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('cades_toileting', array('Custom_CADES_ToiletingCommon', 'process_callback'));

if (ModuleManager::is_installed('Custom_CADES_VitalSigns')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('cades_vitalsigns', array('Custom_CADES_VitalSignsCommon', 'process_callback'));

if (ModuleManager::is_installed('Custom_CADES_Medications')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('cades_medications', array('Custom_CADES_MedicationsCommon', 'process_callback'));

if (ModuleManager::is_installed('Custom_JobSearch_AdvertisingLog')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('custom_jobsearch_advertisinglog', array('Custom_JobSearch_AdvertisingLogCommon', 'submit_advertisinglog'));

if (ModuleManager::is_installed('Custom_JobSearch')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('custom_jobsearch', array('Custom_JobSearchCommon', 'submit_jobsearch'));

if (ModuleManager::is_installed('Custom_PersonalEquipment_Disbursement')!=-1) {
	Utils_RecordBrowserCommon::register_processing_callback('custom_personalequipment_disbur_items', array('Custom_PersonalEquipment_DisbursementCommon', 'submit_disbursement_items'));
	Utils_RecordBrowserCommon::register_processing_callback('custom_personalequipment_disbursement', array('Custom_PersonalEquipment_DisbursementCommon', 'submit_equipment'));
}

if (ModuleManager::is_installed('Custom_PersonalEquipment')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('custom_personalequipment', array('Custom_PersonalEquipmentCommon', 'submit_equipment'));

if (ModuleManager::is_installed('Custom_Projects_ChangeOrders')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('custom_changeorders', array('Custom_Projects_ChangeOrdersCommon', 'submit_co'));

if (ModuleManager::is_installed('Custom_Projects_ProgBilling')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('custom_projects_progbilling', array('Custom_Projects_ProgBillingCommon', 'submit_billing'));

if (ModuleManager::is_installed('Custom_Projects_ShopEquipment')!=-1) {
	Utils_RecordBrowserCommon::register_processing_callback('custom_shopequipment_rental', array('Custom_Projects_ShopEquipmentCommon', 'submit_equipment_rental'));
	Utils_RecordBrowserCommon::register_processing_callback('custom_shopequipment', array('Custom_Projects_ShopEquipmentCommon', 'submit_equipment'));
}

if (ModuleManager::is_installed('Custom_Projects_Tickets')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('custom_tickets', array('Custom_Projects_TicketsCommon', 'submit_ticket'));

if (ModuleManager::is_installed('Custom_TasksModified')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('task', array('Custom_TasksModifiedCommon', 'submit_task'));

if (ModuleManager::is_installed('Premium_Apartments')!=-1) {
	Utils_RecordBrowserCommon::register_processing_callback('premium_apartments_agent', array('Premium_ApartmentsCommon', 'agent_processing'));
	Utils_RecordBrowserCommon::register_processing_callback('premium_apartments_rental', array('Premium_ApartmentsCommon', 'rental_processing'));
}

if (ModuleManager::is_installed('Premium_Checklist')!=-1) {
    Utils_RecordBrowserCommon::register_processing_callback('premium_checklist_list_entry', array('Premium_ChecklistCommon', 'submit_list_entry'));
    Utils_RecordBrowserCommon::register_processing_callback('premium_checklist_list', array('Premium_ChecklistCommon', 'submit_list'));
    Utils_RecordBrowserCommon::register_processing_callback('premium_checklist_item', array('Premium_ChecklistCommon', 'submit_item'));
}

if (ModuleManager::is_installed('Premium_GCProjects')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('gc_projects', array('Premium_GCProjectsCommon', 'submit_project'));

if (ModuleManager::is_installed('Premium_ListManager')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('premium_listmanager', array('Premium_ListManagerCommon', 'submit_listmanager'));

if (ModuleManager::is_installed('Premium_Projects_Tickets')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('premium_tickets', array('Premium_Projects_TicketsCommon', 'submit_ticket'));

if (ModuleManager::is_installed('Premium_Projects_Timesheet')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('premium_projects_timesheet', array('Premium_Projects_TimesheetCommon', 'processing_timesheet'));

if (ModuleManager::is_installed('Premium_Relations')!=-1) {
	Utils_RecordBrowserCommon::register_processing_callback('relations', array('Premium_RelationsCommon', 'process_request'));
	Utils_RecordBrowserCommon::register_processing_callback('relations_types', array('Premium_RelationsCommon', 'process_request_types'));
}

if (ModuleManager::is_installed('Premium_SalesOpportunity')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('premium_salesopportunity', array('Premium_SalesOpportunityCommon', 'submit_salesopportunity'));

if (ModuleManager::is_installed('Premium_SchoolRegister')!=-1)
	Utils_RecordBrowserCommon::register_processing_callback('premium_schoolregister_schedule', array('Premium_SchoolRegisterCommon', 'schedule_processing'));

if (ModuleManager::is_installed('Premium_Warehouse_eCommerce')!=-1) {
	Utils_RecordBrowserCommon::register_processing_callback('premium_ecommerce_products', array('Premium_Warehouse_eCommerceCommon', 'submit_products_position'));
	Utils_RecordBrowserCommon::register_processing_callback('premium_ecommerce_parameters', array('Premium_Warehouse_eCommerceCommon', 'submit_parameters_position'));
	Utils_RecordBrowserCommon::register_processing_callback('premium_ecommerce_parameter_groups', array('Premium_Warehouse_eCommerceCommon', 'submit_parameter_groups_position'));
	Utils_RecordBrowserCommon::register_processing_callback('premium_ecommerce_pages', array('Premium_Warehouse_eCommerceCommon', 'submit_pages_position'));
	Utils_RecordBrowserCommon::register_processing_callback('premium_ecommerce_polls', array('Premium_Warehouse_eCommerceCommon', 'submit_polls_position'));
	Utils_RecordBrowserCommon::register_processing_callback('premium_ecommerce_boxes', array('Premium_Warehouse_eCommerceCommon', 'submit_boxes_position'));
	Utils_RecordBrowserCommon::register_processing_callback('premium_ecommerce_banners', array('Premium_Warehouse_eCommerceCommon','banners_processing'));
	Utils_RecordBrowserCommon::register_processing_callback('premium_ecommerce_users', array('Premium_Warehouse_eCommerceCommon', 'submit_user'));
	Utils_RecordBrowserCommon::register_processing_callback('premium_ecommerce_3rdp_info', array('Premium_Warehouse_eCommerceCommon', 'submit_3rdp_info'));
}

if (ModuleManager::is_installed('Premium_Warehouse_Items')!=-1) {
	Utils_RecordBrowserCommon::register_processing_callback('premium_warehouse_items', array('Premium_Warehouse_ItemsCommon', 'submit_item'));
	Utils_RecordBrowserCommon::register_processing_callback('premium_warehouse_items_categories', array('Premium_Warehouse_ItemsCommon', 'submit_position'));
}

if (ModuleManager::is_installed('Premium_Warehouse_Items_Orders')!=-1) {
	Utils_RecordBrowserCommon::register_processing_callback('premium_warehouse_items_orders', array('Premium_Warehouse_Items_OrdersCommon', 'submit_order'));
	Utils_RecordBrowserCommon::register_processing_callback('premium_warehouse_items_orders_details', array('Premium_Warehouse_Items_OrdersCommon', 'submit_order_details'));
}

            if (ModuleManager::is_installed('Premium_Warehouse_Wholesale')!=-1)
            	Utils_RecordBrowserCommon::register_processing_callback('premium_warehouse_distributor', array('Premium_Warehouse_WholesaleCommon', 'submit_distributor'));
        }
    }

    if (ModuleManager::is_installed('Premium_Projects_Tickets')!=-1) {
	}

}

function update_from_1_1_0_to_1_1_1() {
    if (ModuleManager::is_installed('Apps_Shoutbox')>=0) {
    	PatchDBAddColumn('apps_shoutbox_messages','to_user_login_id','I4');
    }
    if (ModuleManager::is_installed('CRM_Contacts')>=0)
	    Utils_RecordBrowserCommon::register_processing_callback('company', array('CRM_ContactsCommon', 'submit_company'));
    if (ModuleManager::is_installed('CRM_Contacts')>=0) {
		ob_start();
    	@DB::CreateIndex('contact_data_1__f_login_idx','contact_data_1','f_login,active');
		ob_get_clean();
    }
    DB::Execute('DELETE FROM modules WHERE name="Libs_FPDF"');
    DB::Execute('DELETE FROM modules WHERE name = "Tests_FPDF"');
    DB::Execute('DELETE FROM available_modules WHERE name = "Libs_FPDF"');
    DB::Execute('DELETE FROM available_modules WHERE name = "Tests_FPDF"');

}

function update_from_1_1_1_to_1_1_2() {
    if (ModuleManager::is_installed('CRM_Roundcube')>=0) {

		$fields = array(
			array('name' => 'Record Type', 		'type'=>'text', 'param'=>'64', 'required'=>false, 'visible'=>false, 'filter'=>true, 'extra'=>false),
			array('name' => 'Record ID', 		'type'=>'integer', 'filter'=>false, 'required'=>false, 'extra'=>false, 'visible'=>false),
			array('name' => 'Nickname', 		'type'=>'text', 'required'=>true, 'param'=>'64', 'extra'=>false, 'visible'=>true, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_nickname')),
			array('name' => 'Email', 			'type'=>'text', 'required'=>true, 'param'=>'128', 'extra'=>false, 'visible'=>true, 'display_callback'=>array('CRM_ContactsCommon', 'display_email'), 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_email'))
		);

		Utils_RecordBrowserCommon::install_new_recordset('rc_multiple_emails', $fields);
		
		Utils_RecordBrowserCommon::set_favorites('rc_multiple_emails', true);
		Utils_RecordBrowserCommon::set_caption('rc_multiple_emails', 'Mail addresses');
		Utils_RecordBrowserCommon::set_icon('rc_multiple_emails', Base_ThemeCommon::get_template_filename('CRM/Roundube', 'icon.png'));

		Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'view', 'ACCESS:employee', array(), array('record_type', 'record_id'));
		Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'add', 'ACCESS:employee', array(), array('record_type', 'record_id'));
		Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'edit', 'ACCESS:employee', array(), array('record_type', 'record_id'));
		Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'delete', 'ACCESS:employee');

		Utils_RecordBrowserCommon::new_addon('contact', 'CRM/Roundcube', 'mail_addresses_addon', 'Mail addresses');
		Utils_RecordBrowserCommon::new_addon('company', 'CRM/Roundcube', 'mail_addresses_addon', 'Mail addresses');
    }

if (ModuleManager::is_installed('Utils/RecordBrowser')>=0) {

$trans = array(
'CRM/Assets/'=>'crm_assets/',
'CRM/Company/'=>'company/',
'CRM/Contact/'=>'contact/',
'CRM/Calendar/Event/'=>'crm_meeting/',
'CRM/PhoneCall/'=>'phonecall/',
'CRM/Tasks/'=>'task/',
'Custom/CADES/Diagnosis/'=>'cades_diagnosis/',
'CADES/Diet/'=>'cades_diet/',
'CADES/Hospitalizations/'=>'cades_hospitalizations/',
'CADES/Immunizations/'=>'cades_immunizations/',
'CADES/Incidents/'=>'cades_incidents/',
'CADES/Insurance/'=>'cades_insurance/',
'Custom/CADES/Issues/'=>'cades_issues/',
'CADES/MedicalTests/'=>'cades_medicaltests/',
'CADES/Medications/'=>'cades_medications/',
'CADES/Reviews/'=>'cades_reviews/',
'CADES/Services/'=>'cades_services/',
'CADES/Toileting/'=>'cades_toileting/',
'CADES/VitalSigns/'=>'cades_vitalsigns/',
'Custom/JobSearch/AdvertisingLog'=>'custom_jobsearch_advertisinglog/',
'Custom/Projects/ChangeOrders/'=>'custom_changeorders/',
'Custom/Projects/LiftEquipment/'=>'custom_equipment/',
'Custom/Projects/ShopEquipment/'=>'custom_shopequipment/',
'Custom/Projects/Tickets'=>'custom_tickets/',
'Custom/JobSearch'=>'custom_jobsearch/',
'Custom/MonthlyCost/'=>'custom_monthlycost/',
'Custom/Projects/'=>'custom_projects/',
'Premium/Apartments/Agent/'=>'premium_apartments_agent/',
'Premium/Apartments/Rental/'=>'premium_apartments_rental/',
'Premium/Apartments/Apartment/'=>'premium_apartments_apartment/',
'Premium/GCProjects/'=>'gc_projects/',
'Premium/ListManager/'=>'premium_listmanager/',
'Premium/Projects/Tickets'=>'premium_tickets/',
'Premium/Projects/'=>'premium_projects/',
'Premium/SchooRegister/Lesson'=>'premium_schoolregister_lesson/',
'Premium/Warehouse/eCommerce/Products/'=>'premium_ecommerce_products/',
'Premium/Warehouse/eCommerce/ProductsDesc/'=>'premium_ecommerce_descriptions/',
'Premium/Warehouse/eCommerce/Pages/'=>'premium_ecommerce_pages/',
'Premium/Warehouse/eCommerce/PagesDesc/'=>'premium_ecommerce_pages_data/',
'Premium/Warehouse/Items/Orders/'=>'premium_warehouse_items_orders/',
'Premium/Warehouse/Items/'=>'premium_warehouse_items/',
'Premium/Warehouse/Wholesale/'=>'premium_warehouse_distributor/',
'Premium/Warehouse/'=>'premium_warehouse/',
'Tests/Bugtrack/'=>'bugtrack/'
);

$ret = DB::Execute('SELECT * FROM utils_attachment_link');
while ($row = $ret->FetchRow()) {
	foreach ($trans as $k=>$v) {
		$old_local = $row['local'];
		$row['local'] = str_replace($k, $v, $row['local']);
		if ($row['local']!=$old_local)
			DB::Execute('UPDATE utils_attachment_link SET local=%s WHERE id=%d', array($row['local'], $row['id']));
	}
}

foreach ($trans as $k=>$v) {
	$path = explode('/', $k);
	$last = array_pop($path);
	if(!file_exists('data/Utils_Attachment/'.implode('/',$path)) || !is_dir('data/Utils_Attachment/'.implode('/',$path))) continue;
	@mkdir('data/Utils_Attachment/'.$v);
	$dirs = scandir('data/Utils_Attachment/'.implode('/',$path));
	foreach ($dirs as $d) {
		if ($d=='.' || $d=='..') continue;
		$new = str_replace($last,'',$d, $count);
		if ($count!=1) continue;
		if ($new)
			rename('data/Utils_Attachment/'.implode('/',$path).'/'.$d, 'data/Utils_Attachment/'.$v.$new);
	}
}
}
}

$versions[] = '1.1.3';
function update_from_1_1_2_to_1_1_3() {
    if( ModuleManager::is_installed('Base_Theme')>=0 ) {
        Base_ThemeCommon::install_default_theme_common_files('modules/Base/Theme/','images');
    }

    if (ModuleManager::is_installed('CRM_Meeting')>=0) {
        DB::Execute('UPDATE crm_meeting_field SET visible=1 WHERE field="Date" OR field="Time"');
        DB::Execute('UPDATE crm_meeting_field SET visible=0 WHERE field="Recurrence type" OR field="Recurrence end" OR field="Recurrence hash"');
    }

    if (ModuleManager::is_installed('CRM_Roundcube')>=0) {
        Utils_RecordBrowserCommon::set_QFfield_callback('rc_accounts', 'Security', array('CRM_RoundcubeCommon','QFfield_security'));
        Utils_RecordBrowserCommon::new_record_field('rc_accounts',
            array('name'=>'Email',             'type'=>'text', 'extra'=>false, 'visible'=>true, 'required'=>true, 'param'=>128, 'display_callback'=>array('CRM_ContactsCommon', 'display_email'), 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_email'),'position'=>'Epesi User')
            );

        $rec = Utils_RecordBrowserCommon::get_records('rc_accounts');
        foreach($rec as $r) {
            if(preg_match('/@/',$r['login']))
                $email = $r['login'];
            else
                $email = $r['login'].'@'.$r['server'];
            Utils_RecordBrowserCommon::update_record('rc_accounts',$r['id'],array('email'=>$email));
        }
    }

    if (ModuleManager::is_installed('Premium_SalesOpportunity')>=0) {
	    Utils_RecordBrowserCommon::register_processing_callback('contact', array('Premium_SalesOpportunityCommon', 'submit_contact'));
    	Utils_RecordBrowserCommon::register_processing_callback('company', array('Premium_SalesOpportunityCommon', 'submit_company'));
    }
}

$versions[] = '1.1.4';
function update_from_1_1_3_to_1_1_4() {

    if( ModuleManager::is_installed('Base_Theme') ) {
        Base_ThemeCommon::install_default_theme_common_files('modules/Base/Theme/','images');
    }

    if (ModuleManager::is_installed('CRM_Contacts')>=0) {
        if(!DB::GetOne('SELECT 1 FROM contact_field WHERE field=%s',array('Related Companies')) && !DB::GetOne('SELECT 1 FROM contact_field WHERE field=%s',array('Additional Work'))) {
            Utils_RecordBrowserCommon::new_record_field('contact',
		        	array('name'=>'Additional Work', 	'type'=>'crm_company', 'param'=>array('field_type'=>'multiselect'), 'required'=>false, 'extra'=>false, 'visible'=>false, 'filter'=>true,'position'=>'First Name')
            );
            DB::Execute('UPDATE contact_data_1 SET f_additional_work=f_company_name');

            Utils_RecordBrowserCommon::delete_record_field('contact','Company Name');
            Utils_RecordBrowserCommon::new_record_field('contact',
		        	array('name'=>'Company Name', 	'type'=>'crm_company', 'param'=>array('field_type'=>'select'), 'required'=>false, 'extra'=>false, 'visible'=>true, 'filter'=>true,'position'=>'First Name')
            );

            $ret = Utils_RecordBrowserCommon::get_records('contact',array(),array('additional_work','id','first_name','last_name'));
            foreach($ret as $r) {
                if(count($r['additional_work'])>0) {
                    Utils_RecordBrowserCommon::update_record('contact',$r['id'],array('company_name'=>array_shift($r['additional_work']),'additional_work'=>$r['additional_work']));
                    if(count($r['additional_work'])>0)
                        error_log($r['id'].' '.$r['first_name'].' '.$r['last_name']."\n",3,DATA_DIR.'/additional_work.log');
                }
            }        
        }

        if(DB::GetOne('SELECT 1 FROM contact_field WHERE field=%s',array('Additional Work'))) {
            Utils_RecordBrowserCommon::new_record_field('contact',
		        	array('name'=>'Related Companies', 	'type'=>'crm_company', 'param'=>array('field_type'=>'multiselect'), 'required'=>false, 'extra'=>false, 'visible'=>false, 'filter'=>true,'position'=>'Additional Work')
            );
            DB::Execute('UPDATE contact_data_1 SET f_related_companies=f_additional_work');

            Utils_RecordBrowserCommon::delete_record_field('contact','Additional Work');
        }
    }
    
    if (ModuleManager::is_installed('CRM_Meeting')>=0) {
        DB::Execute('UPDATE crm_meeting_field SET visible=1 WHERE field="Date" OR field="Time"');
        DB::Execute('UPDATE crm_meeting_field SET visible=0 WHERE field="Recurrence type" OR field="Recurrence end" OR field="Recurrence hash"');
    }
    
    if (ModuleManager::is_installed('CRM_PhoneCall')>=0) {
        Utils_RecordBrowserCommon::new_record_field('phonecall',
			array('name'=>'Related to', 'type'=>'crm_company_contact', 'param'=>array('field_type'=>'multiselect'), 'extra'=>false, 'visible'=>true)
		);
    }

    if(ModuleManager::is_installed('CRM_Roundcube')>=0) {

        Utils_RecordBrowserCommon::new_record_field('rc_accounts',
            array('name'=>'Advanced', 'type'=>'page_split')
        );

        Utils_RecordBrowserCommon::new_record_field('rc_accounts',
            array('name'=>'IMAP Root', 'type'=>'text', 'param'=>32, 'extra'=>true, 'visible'=>false)
        );

        Utils_RecordBrowserCommon::new_record_field('rc_accounts',
            array('name'=>'IMAP Delimiter', 'type'=>'text', 'param'=>8, 'extra'=>true, 'visible'=>false)
        );


        Utils_RecordBrowserCommon::new_record_field('rc_accounts',
            array('name'=>'Email',             'type'=>'text', 'extra'=>false, 'visible'=>true, 'required'=>true, 'param'=>128,'position'=>'Epesi User')
            );

        $rec = Utils_RecordBrowserCommon::get_records('rc_accounts');
        foreach($rec as $r) {
            if($r['server']=='mail.cadeservices.org') $r['server'] = 'cadeservices.org';
            if(preg_match('/@/',$r['login']))
                $email = $r['login'];
            else
                $email = $r['login'].'@'.$r['server'];
            Utils_RecordBrowserCommon::update_record('rc_accounts',$r['id'],array('email'=>$email));
        }

        Utils_RecordBrowserCommon::new_addon('rc_mails', 'CRM/Roundcube', 'attachments_addon', 'Attachments');

        Utils_RecordBrowserCommon::set_QFfield_callback('rc_accounts', 'Security', array('CRM_RoundcubeCommon','QFfield_security'));
    }

    if (ModuleManager::is_installed('CRM_Tasks')>=0) {
    	Utils_RecordBrowserCommon::new_filter('task', 'Employees');
    	Utils_RecordBrowserCommon::new_filter('task', 'Priority');
    }

    if (ModuleManager::is_installed('CRM_Meeting')>=0) {
    	Utils_RecordBrowserCommon::new_filter('crm_meeting', 'Employees');
    }

    if (ModuleManager::is_installed('CRM_Contacts_AccountManager')>=0) {
	    Utils_RecordBrowserCommon::new_filter('company', 'Account Manager');
    	Utils_RecordBrowserCommon::new_browse_mode_details_callback('company', 'CRM/Contacts/AccountManager', 'browse_mode_details');
    }

    if (ModuleManager::is_installed('CRM_PhoneCall')>=0) {
	    Utils_RecordBrowserCommon::new_filter('phonecall', 'Employees');
    }

    if (ModuleManager::is_installed('Custom_Projects_Tickets')>=0) {
	    Utils_RecordBrowserCommon::new_filter('custom_tickets', 'Assigned To');
    }
    
    if (ModuleManager::is_installed('Premium_Relations')>=0) {
        Utils_RecordBrowserCommon::register_processing_callback('relations_types', array('Premium_RelationsCommon', 'process_request_types'));
    }
    
    if (ModuleManager::is_installed('Premium_SalesOpportunity')>=0) {
	    Utils_RecordBrowserCommon::new_addon('company', 'Premium/SalesOpportunity', 'company_addon', 'Sales Opportunities');
    	Utils_RecordBrowserCommon::new_addon('contact', 'Premium/SalesOpportunity', 'contact_addon', 'Sales Opportunities');
        DB::Execute('UPDATE recordbrowser_addon SET label=%s WHERE tab=%s AND module=%s AND func=%s', array('Premium_SalesOpportunityCommon::activities_addon_label', 'premium_salesopportunity', 'Premium_SalesOpportunity', 'activities_addon'));
        Utils_RecordBrowserCommon::set_display_callback('premium_salesopportunity', 'Employees', array('Premium_SalesOpportunityCommon','display_employees'));
    }

    if (ModuleManager::is_installed('Utils_Watchdog')>=0) {
		ob_start();
        @DB::CreateIndex('utils_watchdog_event__internal_id__idx', 'utils_watchdog_event', 'internal_id');
		ob_get_clean();
    }

    if (ModuleManager::is_installed('Utils_RecordBrowser')>=0) {
        $tabs = DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties');
        foreach ($tabs as $t) {
        	@DB::Execute('ALTER TABLE '.$t.'_favorite ADD CONSTRAINT  FOREIGN KEY ('.$t.'_id) REFERENCES '.$t.'_data_1 (id)');
        	@DB::Execute('ALTER TABLE '.$t.'_recent ADD CONSTRAINT FOREIGN KEY ('.$t.'_id) REFERENCES '.$t.'_data_1 (id)');
        }
    }
}

$versions[] = '1.1.5';
function update_from_1_1_4_to_1_1_5() {
    DB::Execute('DELETE FROM modules WHERE name=%s', array('Base_MaintenanceMode'));

    if (DB::GetOne('SELECT name FROM modules WHERE name=%s', array('Base_MaintenanceMode_Administrator'))) {
	    Base_ThemeCommon::uninstall_default_theme('Base_MaintenanceMode_Administrator');
    	DB::Execute('DELETE FROM modules WHERE name=%s', array('Base_MaintenanceMode_Administrator'));
    }
    
    if (ModuleManager::is_installed('Utils_Watchdog')>=0) {
        ob_start();
        @DB::CreateIndex('utils_watchdog_event__internal_id__idx', 'utils_watchdog_event', 'internal_id');
        ob_end_clean();
    }
    
    if(ModuleManager::is_installed('CRM_Contacts')>=0) {
        $pp = DB::GetOne('SELECT 1 FROM company_field WHERE field=%s', array('Parent Company'));
        if($pp) {
            if (ModuleManager::is_installed('CRM_Contacts_ParentCompany')==-1) {
                DB::Execute('INSERT INTO modules(name,version,priority) VALUES ("CRM_Contacts_ParentCompany",0,0)');
            }
            DB::Execute('UPDATE company_field SET param="company::Company Name;CRM_Contacts_ParentCompanyCommon::parent_company_crits" WHERE field="Parent Company"');
        }
    }
    
    ob_start();
    ModuleManager::install('Libs_CKEditor');
    DB::Execute('DELETE FROM modules WHERE name=%s', array('Libs_FCKeditor'));
    ob_end_clean();

    if(ModuleManager::is_installed('CRM_Roundcube')>=0) {
        @Utils_RecordBrowserCommon::new_record_field('rc_mails',
            array(
                'name'=>'From',
                'type'=>'text',
                'param'=>128,
                'extra'=>false,
                'visible'=>false,
                'required'=>false
            ));
        @Utils_RecordBrowserCommon::new_record_field('rc_mails',
            array(
                'name'=>'To',
                'type'=>'text',
                'param'=>512,
                'extra'=>false,
                'visible'=>false,
                'required'=>false
            ));
        @Utils_RecordBrowserCommon::new_record_field('rc_accounts',
            array('name'=>'Archive on sending', 'type'=>'checkbox', 'extra'=>true, 'visible'=>false, 'position'=>'Advanced'));
        DB::Execute('UPDATE rc_accounts_data_1 SET f_archive_on_sending=1');
    }
    
    if(ModuleManager::is_installed('CRM_Calendar')!=-1) {
        if (ModuleManager::is_installed('CRM_Tasks')!=-1)
        	CRM_CalendarCommon::new_event_handler('Tasks', array('CRM_TasksCommon', 'crm_calendar_handler'));

        if (ModuleManager::is_installed('CRM_PhoneCall')!=-1)
        	CRM_CalendarCommon::new_event_handler('Phonecalls', array('CRM_PhoneCallCommon', 'crm_calendar_handler'));

        if (ModuleManager::is_installed('Premium_Projects_Tickets')!=-1)
        	CRM_CalendarCommon::new_event_handler('Tickets', array('Premium_Projects_TicketsCommon', 'crm_calendar_handler'));
    }
    
    if (ModuleManager::is_installed('Base_Lang')>=0) {
        if (is_dir(DATA_DIR.'/Base_Lang/base')) return;

        mkdir(DATA_DIR.'/Base_Lang/base');
        mkdir(DATA_DIR.'/Base_Lang/custom');

        $data_dir = DATA_DIR.'/Base_Lang/';
        $content = scandir($data_dir);
        foreach ($content as $name){
        	if ($name == '.' || $name == '..') continue;
        	$dot = strpos($name,'.');
        	if (strtolower(substr($name,$dot+1))!='php') continue;
        	$langcode = substr($name,0,$dot);
        	if (!$langcode) continue;
        	rename($data_dir.$name, $data_dir.'base/'.$name);
        	file_put_contents($data_dir.'custom/'.$name, "<?php\n/* custom translations */\nglobal ".'$custom_translations'.";\n?>");
        }
    }
    
    if (ModuleManager::is_installed('Base_Dashboard')!=-1) {
        $users = DB::GetAssoc('SELECT id,user_login_id FROM base_dashboard_applets WHERE module_name=%s',array('Tools_WhoIsOnline'));
        foreach($users as $id=>$u) {
            if(DB::GetOne('SELECT 1 FROM base_dashboard_applets WHERE module_name=%s AND user_login_id=%d',array('CRM_WhoIsOnline',$u))) {
                DB::Execute('DELETE FROM base_dashboard_settings WHERE applet_id=%d',array($id));
                DB::Execute('DELETE FROM base_dashboard_applets WHERE id=%d',array($id));
            } else {
                DB::Execute('UPDATE base_dashboard_applets SET module_name=%s WHERE id=%d',array('CRM_WhoIsOnline',$id));
            }
        }
        DB::Execute('UPDATE base_dashboard_default_applets SET module_name=%s WHERE module_name=%s',array('CRM_WhoIsOnline','Tools_WhoIsOnline'));
    }

    if(ModuleManager::is_installed('CRM_Roundcube')>=0) {
	    Utils_RecordBrowserCommon::set_display_callback('rc_accounts', 'Password', array('CRM_RoundcubeCommon','display_password'));
    	Utils_RecordBrowserCommon::set_display_callback('rc_accounts', 'SMTP Password', array('CRM_RoundcubeCommon','display_password'));
    }

}

$versions[] = '1.1.6';
function update_from_1_1_5_to_1_1_6() {
    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('CRM_MailClient'))) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('CRM_MailClient'));
		$ret = DB::GetCol('SELECT tab FROM crm_mailclient_addons');
		foreach($ret as $r) {
			Utils_RecordBrowserCommon::delete_addon($r, 'CRM/MailClient', 'rb_addon');
		}
		Base_ThemeCommon::uninstall_default_theme('CRM_MailClient');
		Utils_WatchdogCommon::unregister_category('crm_mailclient');
		Utils_RecordBrowserCommon::delete_addon('contact', 'CRM/MailClient', 'contact_addon');
		DB::DropTable('crm_mailclient_attachments');
		DB::DropTable('crm_mailclient_rb_mails');
		DB::DropTable('crm_mailclient_mails');
		DB::DropTable('crm_mailclient_addons');
    }
    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Apps_MailClient'))) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Apps_MailClient'));
		$ret = true;
		$ret &= DB::DropTable('apps_mailclient_filter_rules');
		$ret &= DB::DropTable('apps_mailclient_filter_actions');
		$ret &= DB::DropTable('apps_mailclient_filters');
		$ret &= DB::DropTable('apps_mailclient_accounts');
		Variable::delete('max_mail_size');
		Base_ThemeCommon::uninstall_default_theme('Apps_MailClient');
    }
    DB::Execute('DELETE FROM modules WHERE name=%s',array('Base_Navigation'));
    DB::Execute('DELETE FROM modules WHERE name=%s',array('Utils_DirtyRead'));

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Premium_Freeconet')) && !is_dir('modules/Premium/Freeconet')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Premium_Freeconet'));
        Base_ThemeCommon::uninstall_default_theme('Premium_Freeconet');
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('CRM_Fax')) && !is_dir('modules/CRM/Fax')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('CRM_Fax'));
        Base_ThemeCommon::uninstall_default_theme('CRM_Fax');
		ModuleManager::remove_data_dir('CRM_Fax');
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Utils_Comment')) && !is_dir('modules/Utils/Comment')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Utils_Comment'));
        Base_ThemeCommon::uninstall_default_theme('Utils_Comment');
        DB::DropTable('comment_report');
		DB::DropTable('comment');
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Apps_Forum')) && !is_dir('modules/Apps/Forum')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Apps_Forum'));
        Base_ThemeCommon::uninstall_default_theme('Apps_Forum');
		$ret = true;
		$ret &= DB::DropTable('apps_forum_thread');
		$ret &= DB::DropTable('apps_forum_board');
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Apps_Gallery')) && !is_dir('modules/Apps/Gallery')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Apps_Gallery'));
        Base_ThemeCommon::uninstall_default_theme('Apps_Gallery');
		ModuleManager::remove_data_dir('Apps_Gallery');
		DB::DropTable('gallery_shared_media');
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Utils_Gallery')) && !is_dir('modules/Utils/Gallery')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Utils_Gallery'));
        Base_ThemeCommon::uninstall_default_theme('Utils_Gallery');
		ModuleManager::remove_data_dir('Utils_Gallery');
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Utils_Path')) && !is_dir('modules/Utils/Path')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Utils_Path'));
        Base_ThemeCommon::uninstall_default_theme('Utils_Path');
		ModuleManager::remove_data_dir('Utils_Path');
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Libs_Lytebox')) && !is_dir('modules/Libs/Lytebox')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Libs_Lytebox'));
        Base_ThemeCommon::uninstall_default_theme('Libs_Lytebox');
		ModuleManager::remove_data_dir('Libs_Lytebox');
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Apps_StaticPage'))) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Apps_StaticPage'));
        Base_ThemeCommon::uninstall_default_theme('Apps_StaticPage');
		ModuleManager::remove_data_dir('Apps_StaticPage');
		DB::DropTable('apps_staticpage_pages');
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Utils_CustomMenu')) && !is_dir('modules/Utils/CustomMenu')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Utils_CustomMenu'));
        Base_ThemeCommon::uninstall_default_theme('Utils_CustomMenu');
		ModuleManager::remove_data_dir('Utils_CustomMenu');
		$ret = true;
		$ret &= DB::DropTable('utils_custommenu_entry');
		$ret &= DB::DropTable('utils_custommenu_page');
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Apps_TwisterGame')) && !is_dir('modules/Apps/TwisterGame')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Apps_TwisterGame'));
        Base_ThemeCommon::uninstall_default_theme('Apps_TwisterGame');
		ModuleManager::remove_data_dir('Apps_TwisterGame');
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Develop_Translations')) && !is_dir('modules/Develop/Translations')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Develop_Translations'));
        Base_ThemeCommon::uninstall_default_theme('Develop_Translations');
 		ModuleManager::remove_data_dir('Develop_Translations');
		DB::DropTable('develop_translations_list');
		Base_ThemeCommon::uninstall_default_theme('Develop_Translations');
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Develop_ModuleCreator')) && !is_dir('modules/Develop/ModuleCreator')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Develop_ModuleCreator'));
        Base_ThemeCommon::uninstall_default_theme('Develop_ModuleCreator');
 		ModuleManager::remove_data_dir('Develop_ModuleCreator');
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Develop_ModuleEditor')) && !is_dir('modules/Develop/ModuleEditor')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Develop_ModuleEditor'));
        Base_ThemeCommon::uninstall_default_theme('Develop_ModuleEditor');
 		ModuleManager::remove_data_dir('Develop_ModuleEditor');
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Develop_TableBrowserCreator')) && !is_dir('modules/Develop/TableBrowserCreator')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Develop_TableBrowserCreator'));
        Base_ThemeCommon::uninstall_default_theme('Develop_TableBrowserCreator');
 		ModuleManager::remove_data_dir('Develop_TableBrowserCreator');
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Tests')) && !is_dir('modules/Tests')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Tests'));
        Base_ThemeCommon::uninstall_default_theme('Tests');
 		ModuleManager::remove_data_dir('Tests');
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Tests_Bugtrack')) && !is_dir('modules/Tests/Bugtrack')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Tests_Bugtrack'));
        Base_ThemeCommon::uninstall_default_theme('Tests_Bugtrack');
 		ModuleManager::remove_data_dir('Tests_Bugtrack');
		Utils_RecordBrowserCommon::delete_addon('company', 'Tests/Bugtrack', 'company_bugtrack_addon');
		Utils_RecordBrowserCommon::uninstall_recordset('bugtrack');
		Utils_CommonDataCommon::remove('Bugtrack_Status');
    }

    $tests = array('Attachment', 'BookmarkBrowser', 'Calendar', 'Callbacks', 'Callbacks_a', 'Codepress', 'Colorpicker', 'Comment', 'GenericBrowser', 'Image', 'Lang', 'Leightbox', 'Lytebox', 'Menu', 'OpenFlashChart', 'QuickForm', 'Report', 'Search', 'SharedUniqueHref', 'SharedUniqueHref_a', 'TabbedBrowser', 'Tooltip', 'Wizard','Calendar_Event');
    foreach($tests as $t) {
        if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Tests_'.$t)) && !is_dir('modules/Tests/'.str_replace('_','/',$t))) {
            DB::Execute('DELETE FROM modules WHERE name=%s',array('Tests_'.$t));
            Base_ThemeCommon::uninstall_default_theme('Tests_'.$t);
    		ModuleManager::remove_data_dir('Tests_'.$t);
        }
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Utils_CatFile')) && !is_dir('modules/Utils/CatFile')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Utils_CatFile'));
        Base_ThemeCommon::uninstall_default_theme('Utils_CatFile');
		ModuleManager::remove_data_dir('Utils_CatFile');
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Libs_Codepress')) && !is_dir('modules/Utils/CatFile')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Libs_Codepress'));
        Base_ThemeCommon::uninstall_default_theme('Libs_Codepress');
		ModuleManager::remove_data_dir('Libs_Codepress');
    }

    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Utils_Planner')) && !is_dir('modules/Utils/Planner')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Utils_Planner'));
        Base_ThemeCommon::uninstall_default_theme('Utils_Planner');
		ModuleManager::remove_data_dir('Utils_Planner');
    }


    if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array('Utils_ExportXLS')) && !is_dir('modules/Utils/ExportXLS')) {
        DB::Execute('DELETE FROM modules WHERE name=%s',array('Utils_ExportXLS'));
        Base_ThemeCommon::uninstall_default_theme('Utils_ExportXLS');
		ModuleManager::remove_data_dir('Utils_ExportXLS');
    }
}

$versions[] = '1.1.7';
function update_from_1_1_6_to_1_1_7() {
}

$versions[] = '1.1.8';
function update_from_1_1_7_to_1_1_8() {

if(ModuleManager::is_installed('CRM_Roundcube')>=0) {
    if(DATABASE_DRIVER=='mysqlt') {
        @DB::Execute('ALTER TABLE `rc_users` CHANGE `last_login` `last_login` datetime DEFAULT NULL');
        @DB::Execute('UPDATE `rc_users` SET `last_login` = NULL WHERE `last_login` = \'1000-01-01 00:00:00\'');
        @DB::Execute('ALTER TABLE `rc_users` DROP INDEX `username_index`');
        @DB::Execute('ALTER TABLE `rc_users` ADD UNIQUE `username` (`username`, `mail_host`)');
        @DB::Execute('ALTER TABLE `rc_contacts` MODIFY `email` varchar(255) NOT NULL');
        @DB::Execute('TRUNCATE TABLE `rc_messages`');
    } else {
        @DB::Execute('ALTER TABLE rc_users ALTER last_login DROP NOT NULL');
        @DB::Execute('ALTER TABLE rc_users ALTER last_login SET DEFAULT NULL');
        @DB::Execute('DROP INDEX rc_users_username_id_idx');
        @DB::Execute('ALTER TABLE rc_users ADD CONSTRAINT users_username_key UNIQUE (username, mail_host)');
        @DB::Execute('ALTER TABLE rc_contacts ALTER email TYPE varchar(255)');
        @DB::Execute('TRUNCATE rc_messages');
    }
}

if (ModuleManager::is_installed('CRM_Contacts_ParentCompany')>=0) {
    Utils_RecordBrowserCommon::new_addon('company', 'CRM_Contacts_ParentCompany', 'parent_company_addon', 'Child Companies');
}

if (ModuleManager::is_installed('CRM_Contacts')>=0 && ModuleManager::is_installed('CRM_Roundcube')>=0){

if (DB::GetOne('SELECT * FROM contact_field WHERE field=%s', array('Assistant Email'))) {
	$ret = DB::Execute('SELECT * FROM contact_data_1 WHERE f_assistant_email IS NOT NULL');
	while($row = $ret->FetchRow()) {
		Utils_RecordBrowserCommon::new_record('rc_multiple_emails', array('record_type'=>'contact', 'record_id'=>$row['id'], 'nickname'=>'Assistant\'s E-mail', 'email'=>$row['f_assistant_email']));
	}
	Utils_RecordBrowserCommon::delete_record_field('contact', 'Assistant Email');
}

if (DB::GetOne('SELECT * FROM contact_field WHERE field=%s', array('Home Email'))) {
	$ret = DB::Execute('SELECT * FROM contact_data_1 WHERE f_home_email IS NOT NULL');
	while($row = $ret->FetchRow()) {
		Utils_RecordBrowserCommon::new_record('rc_multiple_emails', array('record_type'=>'contact', 'record_id'=>$row['id'], 'nickname'=>'Home E-mail', 'email'=>$row['f_home_email']));
	}
	Utils_RecordBrowserCommon::delete_record_field('contact', 'Home Email');
}

}
}

$versions[] = '1.2.0';
function update_from_1_1_8_to_1_2_0() {
ob_start();
ob_end_clean();

if (ModuleManager::is_installed('Base_Admin')>=0) {
    $tbls = DB::MetaTables('TABLE',true);
    if(!in_array('base_admin_access',$tbls))
        DB::CreateTable('base_admin_access',
        	'id I4 AUTO KEY,'.
        	'module C(128),'.
        	'section C(64),'.
        	'allow I1',
        	array('constraints'=>''));
}

if (ModuleManager::is_installed('Data_Countries')>=0) {
    $countries = array(
		"BC"=>'British Indian Ocean Territory',
		"CS"=>'Cocos Islands',
		"FS"=>'French Southern Territories');

    Utils_CommonDataCommon::extend_array('Countries', $countries, true);
}

DB::Execute('DELETE FROM modules WHERE name=%s', array('Utils_Attachment_Administrator'));
Base_ThemeCommon::uninstall_default_theme('Utils_Attachment_Administrator');
Base_SetupCommon::refresh_available_modules();

}

$versions[] = '1.2.1';
function update_from_1_2_0_to_1_2_1() {
    $tbls = DB::MetaTables('TABLE',true);

    if (ModuleManager::is_installed('Base_Theme_Administrator')>=0) {
        if(!in_array('base_theme_themeup',$tbls))
        DB::CreateTable('base_theme_themeup',
	    'id I4 AUTO KEY,'.
    	    'module C(128)',
        	array('constraints'=>''));
    }

if(ModuleManager::is_installed('CRM_Contacts')>=0) {
    if(!DB::GetOne('SELECT 1 FROM recordbrowser_datatype WHERE type=%s', array('email')))
	Utils_RecordBrowserCommon::register_datatype('email', 'CRM_ContactsCommon', 'email_datatype');
    Utils_RecordBrowserCommon::set_QFfield_callback('contact', 'Email', 'CRM_ContactsCommon::QFfield_unique_email');
    Utils_RecordBrowserCommon::set_QFfield_callback('company', 'Email', 'CRM_ContactsCommon::QFfield_unique_email');
}
if(ModuleManager::is_installed('CRM_Roundcube')>=0) {
    Utils_RecordBrowserCommon::set_QFfield_callback('rc_multiple_emails', 'Email', 'CRM_ContactsCommon::QFfield_unique_email');
}

if (ModuleManager::is_installed('CRM/Roundcube')>=0) {
	Utils_RecordBrowserCommon::delete_addon('rc_mails', 'CRM/Roundcube', 'attachments_addon');
	Utils_RecordBrowserCommon::delete_addon('rc_mails', 'CRM/Roundcube', 'assoc_addon');

	Utils_RecordBrowserCommon::delete_record_field('rc_mails', 'Headers');
	Utils_RecordBrowserCommon::new_addon('rc_mails', 'CRM/Roundcube', 'mail_body_addon', 'Body');
	Utils_RecordBrowserCommon::new_addon('rc_mails', 'CRM/Roundcube', 'assoc_addon', 'Associated records');
	Utils_RecordBrowserCommon::new_addon('rc_mails', 'CRM/Roundcube', 'attachments_addon', 'Attachments');
	Utils_RecordBrowserCommon::new_addon('rc_mails', 'CRM/Roundcube', 'mail_headers_addon', 'Headers');

        if(!DB::GetOne('SELECT 1 FROM rc_accounts_field WHERE field=%s', array('Account Name'))) {
        	Utils_RecordBrowserCommon::new_record_field('rc_accounts',
	        	array('name'=>'Account Name',             'type'=>'text', 'extra'=>false, 'visible'=>true, 'required'=>true, 'param'=>32, 'QFfield_callback'=>array('CRM_RoundcubeCommon','QFfield_account_name'), 'position'=>'Email'));
        	DB::Execute('UPDATE rc_accounts_data_1 SET f_account_name=f_email');
        }
	Utils_RecordBrowserCommon::set_tpl('rc_mails', Base_ThemeCommon::get_template_filename('CRM/Roundcube', 'mails'));
}

if (ModuleManager::is_installed('Base_EpesiStore')>=1) {
    if(!in_array('epesi_store_modules',$tbls))
    DB::CreateTable('epesi_store_modules','
        module_id I4 PRIMARY KEY,
        version I4,
        order_id I4 NOTNULL');

    $d = DB::dict();
    $x = $d->ChangeTableSQL('epesi_store_modules','
        module_id I4 PRIMARY KEY,
        version I4,
        order_id I4 NOTNULL,
        file C(20)');
    if($x) $d->ExecuteSQLArray($x);

}

if (ModuleManager::is_installed('CRM_MailClient')==-1) {
    Utils_WatchdogCommon::unregister_category('crm_mailclient');
}

}

$versions[] = '1.2.2';
function update_from_1_2_1_to_1_2_2() {
    $tbls = DB::MetaTables('TABLE',true);
    if (ModuleManager::is_installed('Base_ModuleDownloader') >= 0) {
        Base_ThemeCommon::uninstall_default_theme('Base/ModuleDownloader');
        ModuleManager::remove_data_dir('Base/ModuleDownloader');
        DB::Execute('DELETE FROM modules WHERE name=%s', 'Base_ModuleDownloader');
    }

    if (ModuleManager::is_installed('Base_User_Login')>=0 && array_key_exists('AUTOLOGIN_ID',DB::MetaColumnNames('user_password'))) {
		$ret = @DB::CreateTable('user_autologin',"user_login_id I NOTNULL, autologin_id C(32) NOTNULL, last_log T, description C(64)",array('constraints' => ', FOREIGN KEY (user_login_id) REFERENCES user_login(id)'));
		if($ret===false) {
			print('Invalid SQL query - user_autologin table install');
			return false;
		}
        $e = DB::Execute('SELECT autologin_id,mobile_autologin_id,user_login_id FROM user_password');
        while($r = $e->FetchRow()) {
            if($r['autologin_id'])
                DB::Execute('INSERT INTO user_autologin(user_login_id,autologin_id) VALUES(%d,%s)',array($r['user_login_id'],$r['autologin_id']));
            if($r['mobile_autologin_id'])
                DB::Execute('INSERT INTO user_autologin(user_login_id,autologin_id) VALUES(%d,%s)',array($r['user_login_id'],$r['mobile_autologin_id']));
        }
        PatchDBDropColumn('user_password','autologin_id');
        PatchDBDropColumn('user_password','mobile_autologin_id');
    }

    if (ModuleManager::is_installed('Base_EpesiStore')>=0)
        DB::Execute('ALTER TABLE `epesi_store_modules` MODIFY `version` varchar(10)');


    if (ModuleManager::is_installed('CRM_Roundcube') >= 0) {
        DB::Execute('UPDATE recordbrowser_addon SET label=%s where label=%s', array('e-mails','Mails'));
        DB::Execute('UPDATE recordbrowser_addon SET label=%s where label=%s', array('e-mail addresses', 'Mail addresses'));
    }

}
//=========================================================================

$versions[] = '1.3';
function update_from_1_2_2_to_1_3() {
        DB::Execute('delete from modules where name="Utils_BookmarkBrowser"');
        @DB::Execute('insert into modules values ("Utils_RecordBrowser_RecordPicker",0,0)');
        @DB::Execute('insert into modules values ("Utils_RecordBrowser_RecordPickerFS",0,0)');
	ob_start();
        ModuleManager::install("Base_EpesiStore");
	ob_end_clean();
        ModuleManager::create_load_priority_array();
	PatchUtil::apply_new();
}

//=========================================================================

$versions[] = '1.4.0';
function update_from_1_3_to_1_4_0() {
	ModuleManager::create_load_priority_array();
	PatchUtil::apply_new();
}

$go=false;
$last_ver = '';
define('CID',false);
define('UPDATING_EPESI',true);
require_once('include.php');
@set_time_limit(0);
try {
$cur_ver = Variable::get('version');
} catch(Exception $s) {
$cur_ver = '0.8.5';
}

//restore innodb tables in case of db reimport
if (strcasecmp(DATABASE_DRIVER,"postgres")!==0) {
	$tbls = DB::MetaTables('TABLE',true);
	foreach($tbls as $t) {
		$tbl = DB::GetRow('SHOW CREATE TABLE '.$t);
		if(!isset($tbl[1]) || preg_match('/ENGINE=myisam/i',$tbl[1]))
			DB::Execute('ALTER TABLE '.$t.' ENGINE = INNODB');
	}
}

ModuleManager::create_load_priority_array();
ModuleManager::create_common_cache();
ob_start();
ModuleManager::load_modules();
ob_end_clean();
foreach($versions as $v) {
	$x = str_replace('.','_',$v);
	if($go) {
		if(is_callable('update_from_'.$last_ver.'_to_'.$x)) {
//			print('Update from '.$last_ver.' to '.$x.'<br>');
			call_user_func('update_from_'.$last_ver.'_to_'.$x);
		}
        Variable::set('version',$v);
	}
	if($v==$cur_ver) $go=true;
	if($v==EPESI_VERSION) $go=false;
	$last_ver = $x;
}
@unlink(DATA_DIR.'/cache/common.php');
@recursive_rmdir(DATA_DIR.'/cache/minify');

if ($cur_ver==EPESI_VERSION && !Base_AclCommon::i_am_sa()) die('Unauthorized access');

themeup();
Base_LangCommon::update_translations();
Base_ThemeCommon::create_cache();
ModuleManager::create_load_priority_array();

Variable::set('version',EPESI_VERSION);

if (!isset($_GET['up'])) print('Tool finished successfully');
?>
