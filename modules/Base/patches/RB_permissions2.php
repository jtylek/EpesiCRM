<?php
if (ModuleManager::is_installed('Utils_RecordBrowser')==-1) return;

if (DB::GetOne('SELECT 1 FROM contact_field WHERE field=%s', array('Username'))) return;

$tables = DB::GetAssoc('SELECT tab, tab FROM recordbrowser_table_properties');

foreach ($tables as $tab) {

	@DB::DropTable($tab.'_access_clearance');
	@DB::DropTable($tab.'_access_fields');
	@DB::DropTable($tab.'_access');

	DB::CreateTable($tab.'_access',
				'id I AUTO KEY,'.
				'action C(16),'.
				'crits C(255)',
				array('constraints'=>''));
	DB::CreateTable($tab.'_access_fields',
				'rule_id I,'.
				'block_field C(32)',
				array('constraints'=>', FOREIGN KEY (rule_id) REFERENCES '.$tab.'_access(id)'));
	DB::CreateTable($tab.'_access_clearance',
				'rule_id I,'.
				'clearance C(32)',
				array('constraints'=>', FOREIGN KEY (rule_id) REFERENCES '.$tab.'_access(id)'));
				
}

Utils_RecordBrowserCommon::new_record_field('contact', array('name'=>'Login Panel',		'type'=>'page_split', 'param'=>1));

$fields = array(
	array('name'=>'Username', 		'type'=>'calculated', 'required'=>false, 'extra'=>true, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_username')),
	array('name'=>'Set Password', 	'type'=>'calculated', 'required'=>false, 'extra'=>true, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_password')),
	array('name'=>'Confirm Password','type'=>'calculated', 'required'=>false, 'extra'=>true, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_repassword')),
	array('name'=>'Admin', 			'type'=>'calculated', 'required'=>false, 'extra'=>true, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_admin')),
	array('name'=>'Access', 		'type'=>'multiselect', 'required'=>false, 'param'=>Utils_RecordBrowserCommon::multiselect_from_common('Contacts/Access'), 'extra'=>true, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_access'))
);

foreach ($fields as $f)
	Utils_RecordBrowserCommon::new_record_field('contact', $f);

$pos = DB::GetOne('SELECT position FROM contact_field WHERE field=%s', array('Login'));
$pos2 = DB::GetOne('SELECT position FROM contact_field WHERE field=%s', array('Username'));
DB::Execute('UPDATE contact_field SET position=position-1 WHERE position>%d AND position<%d', array($pos, $pos2));
DB::Execute('UPDATE contact_field SET position=%d, style=%s WHERE field=%s', array($pos2-1,'','Login'));

Utils_CommonDataCommon::extend_array('Contacts/Access',array('manager'=>'Manager'));

$ret = DB::Execute('SELECT * FROM user_login');
while ($row = $ret->FetchRow()) {
	$aid = Base_AclCommon::get_acl_user_id($row['id']);
	$allow = Base_AclCommon::is_user_in_group($aid, 'Employee Manager');
	$allow |= Base_AclCommon::is_user_in_group($aid, 'Employee Administrator');
	$allow |= Base_AclCommon::is_user_in_group($aid, 'Administrator');
	$allow |= Base_AclCommon::is_user_in_group($aid, 'Super administrator');
	if ($allow) {
		$r2 = DB::Execute('SELECT * FROM contact_data_1 WHERE f_login=%d', array($row['id']));
		while ($r=$r2->FetchRow()) {
			if (!$r['f_access']) $gr = '__manager__';
			else $gr = $r['f_access'].'manager__';
			DB::Execute('UPDATE contact_data_1 SET f_access=%s WHERE id=%d', array($gr, $r['id']));
		}
	}
}

PatchDBDropColumn('recordbrowser_table_properties','access_callback');


Utils_RecordBrowserCommon::add_default_access('crm_assets');

Utils_RecordBrowserCommon::add_access('company', 'view', 'EMPLOYEE', array('(!permission'=>2, '|:Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('company', 'view', 'ALL', array('id'=>'USER_COMPANY'));
Utils_RecordBrowserCommon::add_access('company', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('company', 'edit', 'EMPLOYEE', array('(permission'=>0, '|:Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('company', 'edit', array('ALL','ACCESS:manager'), array('id'=>'USER_COMPANY'));
Utils_RecordBrowserCommon::add_access('company', 'edit', array('EMPLOYEE','ACCESS:manager'), array());
Utils_RecordBrowserCommon::add_access('company', 'delete', 'EMPLOYEE', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('company', 'delete', array('EMPLOYEE','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('contact', 'view', 'EMPLOYEE', array('(!permission'=>2, '|:Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('contact', 'view', 'ALL', array('login'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('contact', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('contact', 'edit', 'EMPLOYEE', array('(permission'=>0, '|:Created_by'=>'USER_ID'), array('access', 'login'));
Utils_RecordBrowserCommon::add_access('contact', 'edit', 'ALL', array('login'=>'USER_ID'), array('company_name', 'related_companies', 'access', 'login'));
Utils_RecordBrowserCommon::add_access('contact', 'edit', array('ALL','ACCESS:manager'), array('company_name'=>'USER_COMPANY'), array('login', 'company_name', 'related_companies'));
Utils_RecordBrowserCommon::add_access('contact', 'edit', array('EMPLOYEE','ACCESS:manager'), array());
Utils_RecordBrowserCommon::add_access('contact', 'delete', 'EMPLOYEE', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('contact', 'delete', array('EMPLOYEE','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('crm_meeting', 'view', 'EMPLOYEE', array('(!permission'=>2, '|employees'=>'USER'));
Utils_RecordBrowserCommon::add_access('crm_meeting', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('crm_meeting', 'edit', 'EMPLOYEE', array('(permission'=>0, '|employees'=>'USER', '|customers'=>'USER'));
Utils_RecordBrowserCommon::add_access('crm_meeting', 'delete', 'EMPLOYEE', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('crm_meeting', 'delete', array('EMPLOYEE','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('phonecall', 'view', 'EMPLOYEE', array('(!permission'=>2, '|employees'=>'USER'));
Utils_RecordBrowserCommon::add_access('phonecall', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('phonecall', 'edit', 'EMPLOYEE', array('(permission'=>0, '|employees'=>'USER', '|customer'=>'USER'));
Utils_RecordBrowserCommon::add_access('phonecall', 'delete', 'EMPLOYEE', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('phonecall', 'delete', array('EMPLOYEE','ACCESS:manager'));

if(ModuleManager::is_installed('CRM_Roundcube')>=0) {
	Utils_RecordBrowserCommon::add_access('rc_accounts', 'view', 'EMPLOYEE', array('epesi_user'=>'USER_ID'));
	Utils_RecordBrowserCommon::add_access('rc_accounts', 'add', 'EMPLOYEE');
	Utils_RecordBrowserCommon::add_access('rc_accounts', 'edit', 'EMPLOYEE', array(), array('epesi_user'));
	Utils_RecordBrowserCommon::add_access('rc_accounts', 'delete', 'EMPLOYEE', array('epesi_user'=>'USER_ID'));

	Utils_RecordBrowserCommon::add_access('rc_mails', 'view', 'EMPLOYEE', array(), array('headers_data'));
	Utils_RecordBrowserCommon::add_access('rc_mails', 'delete', 'EMPLOYEE');

	Utils_RecordBrowserCommon::add_access('rc_mails_assoc', 'view', 'EMPLOYEE', array(), array('recordset'));
	Utils_RecordBrowserCommon::add_access('rc_mails_assoc', 'delete', 'EMPLOYEE');

	Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'view', 'EMPLOYEE');
	Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'add', 'EMPLOYEE');
	Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'edit', 'EMPLOYEE');
	Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'delete', 'EMPLOYEE');

	DB::Execute('UPDATE rc_multiple_emails_field SET type=%s WHERE field=%s OR field=%s', array('hidden', 'Record Type', 'Record ID'));
}

Utils_RecordBrowserCommon::add_access('task', 'view', 'EMPLOYEE', array('(!permission'=>2, '|employees'=>'USER'));
Utils_RecordBrowserCommon::add_access('task', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('task', 'edit', 'EMPLOYEE', array('(permission'=>0, '|employees'=>'USER', '|customers'=>'USER'));
Utils_RecordBrowserCommon::add_access('task', 'delete', 'EMPLOYEE', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('task', 'delete', array('EMPLOYEE','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('premium_projects', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_projects', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_projects', 'edit', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_projects', 'delete', 'EMPLOYEE', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('premium_projects', 'delete', array('EMPLOYEE','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('premium_tickets_testing', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_tickets_testing', 'add', 'EMPLOYEE', array('!ticket[status]'=>6), array('finished_on'));
Utils_RecordBrowserCommon::add_access('premium_tickets_testing', 'edit', 'EMPLOYEE', array(), array('finished_on', 'ticket'));
Utils_RecordBrowserCommon::add_access('premium_tickets_testing', 'delete', 'EMPLOYEE', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('premium_tickets_testing', 'delete', array('EMPLOYEE','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('premium_tickets', 'view', 'EMPLOYEE', array('(!permission'=>2, '|assigned_to'=>'USER', '|ticket_owner'=>'USER'));
Utils_RecordBrowserCommon::add_access('premium_tickets', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_tickets', 'edit', 'EMPLOYEE', array('ticket_owner'=>'USER'));
Utils_RecordBrowserCommon::add_access('premium_tickets', 'edit', 'EMPLOYEE', array('assigned_to'=>'USER'), array('ticket_owner', 'status'));
Utils_RecordBrowserCommon::add_access('premium_tickets', 'edit', 'EMPLOYEE', array('status'=>0, 'permission'=>0), array('ticket_owner', 'status'));
Utils_RecordBrowserCommon::add_access('premium_tickets', 'edit', array('EMPLOYEE','ACCESS:manager'), array('(permission'=>0,'|assigned_to'=>'USER'));
Utils_RecordBrowserCommon::add_access('premium_tickets', 'delete', 'EMPLOYEE', array('ticket_owner'=>'USER'));

if(ModuleManager::is_installed('Premium_Projects_Tickets_Testing')>=0) {
	Utils_RecordBrowserCommon::field_deny_access('premium_tickets', 'Tested', 'edit');
	Utils_RecordBrowserCommon::field_deny_access('premium_tickets', 'Tested', 'add');
}


/** PREMIUM **/

Utils_RecordBrowserCommon::add_default_access('custom_jobsearch_advertisinglog');
Utils_RecordBrowserCommon::add_default_access('custom_jobsearch_advertisinglog_cost');

Utils_RecordBrowserCommon::add_access('custom_jobsearch_advertisinglog_element', 'view', 'EMPLOYEE', array(), array('record_id'));
Utils_RecordBrowserCommon::add_access('custom_jobsearch_advertisinglog_element', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_jobsearch_advertisinglog_element', 'edit', 'EMPLOYEE', array(), array('record_type', 'list_id'));

Utils_RecordBrowserCommon::add_default_access('custom_jobsearch_advertisinglog_history');

Utils_RecordBrowserCommon::add_default_access('custom_jobsearch');

Utils_RecordBrowserCommon::add_access('custom_jobsearch_element', 'view', 'EMPLOYEE', array(), array('record_id'));
Utils_RecordBrowserCommon::add_access('custom_jobsearch_element', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_jobsearch_element', 'edit', 'EMPLOYEE', array(), array('list_id'));

Utils_RecordBrowserCommon::add_access('custom_jobsearch_history', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_jobsearch_history', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_jobsearch_history', 'edit', 'EMPLOYEE', array(), array('target', 'list_id'));
Utils_RecordBrowserCommon::add_access('custom_jobsearch_history', 'delete', 'EMPLOYEE', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('custom_jobsearch_history', 'delete', array('EMPLOYEE','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('custom_merlin_licence', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_merlin_licence', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_merlin_licence', 'edit', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_merlin_licence', 'delete', array('EMPLOYEE','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('custom_merlin_charge', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_merlin_charge', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_merlin_charge', 'edit', 'EMPLOYEE', array(), array('licence'));
Utils_RecordBrowserCommon::add_access('custom_merlin_charge', 'delete', array('EMPLOYEE','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('custom_merlin_charge', 'view', 'EMPLOYEE');

Utils_RecordBrowserCommon::add_default_access('custom_monthlycost');

Utils_RecordBrowserCommon::add_default_access('custom_personalequipment_disbursement');
Utils_RecordBrowserCommon::add_default_access('custom_personalequipment_disbur_items');

Utils_RecordBrowserCommon::add_default_access('custom_personalequipment');

Utils_RecordBrowserCommon::add_default_access('custom_changeorders');

Utils_RecordBrowserCommon::add_default_access('custom_equipment');

Utils_RecordBrowserCommon::add_access('custom_projects_progbilling', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_projects_progbilling', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_projects_progbilling', 'edit', 'EMPLOYEE', array(), array('project_name'));
Utils_RecordBrowserCommon::add_access('custom_projects_progbilling', 'delete', 'EMPLOYEE');

Utils_RecordBrowserCommon::add_access('custom_projects', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_projects', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_projects', 'edit', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_projects', 'delete', 'EMPLOYEE', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('custom_projects', 'delete', array('EMPLOYEE','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('custom_salesgoals', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_salesgoals', 'add', array('EMPLOYEE','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('custom_salesgoals', 'edit', array('EMPLOYEE','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('custom_salesgoals', 'delete', array('EMPLOYEE','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('custom_shopequipment', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_shopequipment', 'add', 'EMPLOYEE', array(), array('job','units_used','total_units_used','rental_date','est_ret_date','last_history_entry'));
Utils_RecordBrowserCommon::add_access('custom_shopequipment', 'edit', 'EMPLOYEE', array(), array('job','units_used','total_units_used','rental_date','est_ret_date','last_history_entry'));
Utils_RecordBrowserCommon::add_access('custom_shopequipment', 'delete', 'EMPLOYEE');

Utils_RecordBrowserCommon::add_access('custom_shopequipment_rental', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_shopequipment_rental', 'add', 'EMPLOYEE', array(), array('return_date'));
Utils_RecordBrowserCommon::add_access('custom_shopequipment_rental', 'edit', 'EMPLOYEE', array(), array('return_date', 'equipment_id'));
Utils_RecordBrowserCommon::add_access('custom_shopequipment_rental', 'delete', 'EMPLOYEE');

Utils_RecordBrowserCommon::add_access('custom_tickets', 'view', 'EMPLOYEE', array('(!permission'=>2, '|assigned_to'=>'USER', '|:Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('custom_tickets', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_tickets', 'edit', 'EMPLOYEE', array('(permission'=>0, '|assigned_to'=>'USER', '|:Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('custom_tickets', 'delete', 'EMPLOYEE', array(':Created_by'=>'USER_ID'));

Utils_RecordBrowserCommon::add_access('custom_projects_visit', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_projects_visit', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('custom_projects_visit', 'edit', 'EMPLOYEE');

Utils_RecordBrowserCommon::add_access('data_tax_rates', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('data_tax_rates', 'add', array('EMPLOYEE','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('data_tax_rates', 'edit', array('EMPLOYEE','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('data_tax_rates', 'delete', array('EMPLOYEE','ACCESS:manager'));

Utils_RecordBrowserCommon::add_default_access('premium_apartments_maintenance');
Utils_RecordBrowserCommon::add_default_access('premium_apartments_agent');
Utils_RecordBrowserCommon::add_default_access('premium_apartments_apartment');

Utils_RecordBrowserCommon::add_access('premium_apartments_rental', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_apartments_rental', 'add', 'EMPLOYEE', array(), array('status'));
Utils_RecordBrowserCommon::add_access('premium_apartments_rental', 'edit', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_apartments_rental', 'delete', 'EMPLOYEE');

Utils_RecordBrowserCommon::add_access('premium_apartments_billing', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_apartments_billing', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_apartments_billing', 'edit', 'EMPLOYEE', array(), array('rental'));
Utils_RecordBrowserCommon::add_access('premium_apartments_billing', 'delete', 'EMPLOYEE');

Utils_RecordBrowserCommon::add_access('premium_apartments_change_order', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_apartments_change_order', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_apartments_change_order', 'edit', 'EMPLOYEE', array(), array('rental'));
Utils_RecordBrowserCommon::add_access('premium_apartments_change_order', 'delete', 'EMPLOYEE');

Utils_RecordBrowserCommon::add_access('premium_campaignmanager_messages', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_campaignmanager_messages', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_campaignmanager_messages', 'edit', 'EMPLOYEE', array(), array('list'));
Utils_RecordBrowserCommon::add_access('premium_campaignmanager_messages', 'delete', 'EMPLOYEE');

Utils_RecordBrowserCommon::add_access('premium_checklist_list_entry', 'view', 'EMPLOYEE', array(), array('completed'));
Utils_RecordBrowserCommon::add_access('premium_checklist_list_entry', 'add', 'EMPLOYEE', array(), array('next_date'));
Utils_RecordBrowserCommon::add_access('premium_checklist_list_entry', 'edit', array('EMPLOYEE','ACCESS:manager'), array(), array('checklist'));
Utils_RecordBrowserCommon::add_access('premium_checklist_list_entry', 'edit', 'EMPLOYEE', array('completed'=>'', 'employee'=>'USER'), array('employee', 'checklist'));
Utils_RecordBrowserCommon::add_access('premium_checklist_list_entry', 'delete', array('EMPLOYEE','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('premium_checklist_list_entry', 'delete', 'EMPLOYEE', array('employee'=>'USER'));

Utils_RecordBrowserCommon::add_access('premium_checklist_item_entry', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_checklist_item_entry', 'edit', 'EMPLOYEE', array('checklist[completed]'=>''), array('checklist', 'item'));

Utils_RecordBrowserCommon::add_default_access('premium_checklist_status');
Utils_RecordBrowserCommon::add_default_access('premium_checklist_list');

Utils_RecordBrowserCommon::add_access('premium_checklist_item', 'view', 'ADMIN', array(), array('recordset'));
Utils_RecordBrowserCommon::add_access('premium_checklist_item', 'add', 'ADMIN', array(), array('recordset'));
Utils_RecordBrowserCommon::add_access('premium_checklist_item', 'edit', 'ADMIN', array(), array('recordset'));
Utils_RecordBrowserCommon::add_access('premium_checklist_item', 'view', 'SUPERADMIN');
Utils_RecordBrowserCommon::add_access('premium_checklist_item', 'add', 'SUPERADMIN');
Utils_RecordBrowserCommon::add_access('premium_checklist_item', 'edit', 'SUPERADMIN');
Utils_RecordBrowserCommon::add_access('premium_checklist_item', 'delete', 'SUPERADMIN');

Utils_RecordBrowserCommon::add_default_access('gc_changeorders');

Utils_RecordBrowserCommon::add_access('gc_projects', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('gc_projects', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('gc_projects', 'edit', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('gc_projects', 'delete', 'EMPLOYEE', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('gc_projects', 'delete', array('EMPLOYEE','ACCESS:manager'));

Utils_RecordBrowserCommon::add_default_access('gc_equipment');

Utils_RecordBrowserCommon::add_access('gc_projects_progbilling', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('gc_projects_progbilling', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('gc_projects_progbilling', 'edit', 'EMPLOYEE', array(), array('project_name'));
Utils_RecordBrowserCommon::add_access('gc_projects_progbilling', 'delete', 'EMPLOYEE');

Utils_RecordBrowserCommon::add_access('gc_salesgoals', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('gc_salesgoals', 'add', array('EMPLOYEE','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('gc_salesgoals', 'edit', array('EMPLOYEE','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('gc_salesgoals', 'delete', array('EMPLOYEE','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('gc_shopequipment', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('gc_shopequipment', 'add', 'EMPLOYEE', array(), array('job','units_used','total_units_used','rental_date','est_ret_date','last_history_entry'));
Utils_RecordBrowserCommon::add_access('gc_shopequipment', 'edit', 'EMPLOYEE', array(), array('job','units_used','total_units_used','rental_date','est_ret_date','last_history_entry'));
Utils_RecordBrowserCommon::add_access('gc_shopequipment', 'delete', 'EMPLOYEE');

Utils_RecordBrowserCommon::add_access('gc_shopequipment_rental', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('gc_shopequipment_rental', 'add', 'EMPLOYEE', array(), array('return_date'));
Utils_RecordBrowserCommon::add_access('gc_shopequipment_rental', 'edit', 'EMPLOYEE', array(), array('return_date', 'equipment_id'));
Utils_RecordBrowserCommon::add_access('gc_shopequipment_rental', 'delete', 'EMPLOYEE');

Utils_RecordBrowserCommon::add_access('gc_tickets', 'view', 'EMPLOYEE', array('(!permission'=>2, '|assigned_to'=>'USER', '|:Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('gc_tickets', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('gc_tickets', 'edit', 'EMPLOYEE', array('(permission'=>0, '|assigned_to'=>'USER', '|:Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('gc_tickets', 'delete', 'EMPLOYEE', array(':Created_by'=>'USER_ID'));

Utils_RecordBrowserCommon::add_access('gc_projects_visit', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('gc_projects_visit', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('gc_projects_visit', 'edit', 'EMPLOYEE');

Utils_RecordBrowserCommon::add_access('premium_listmanager', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_listmanager', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_listmanager', 'edit', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_listmanager', 'delete', 'EMPLOYEE', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('premium_listmanager', 'delete', array('EMPLOYEE','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('premium_listmanager_element', 'view', 'EMPLOYEE', array(), array('record_id'));
Utils_RecordBrowserCommon::add_access('premium_listmanager_element', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_listmanager_element', 'edit', 'EMPLOYEE', array(), array('list_name','record_id'));

Utils_RecordBrowserCommon::add_access('premium_listmanager_history', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_listmanager_history', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_listmanager_history', 'edit', 'EMPLOYEE', array(), array('target', 'list_name'));
Utils_RecordBrowserCommon::add_access('premium_listmanager_history', 'delete', 'EMPLOYEE', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('premium_listmanager_history', 'delete', array('EMPLOYEE','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('premium_logbook', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_logbook', 'add', 'EMPLOYEE', array(), array('date_and_time', 'author'));

Utils_RecordBrowserCommon::add_access('premium_multiple_addresses', 'view', 'EMPLOYEE', array(), array('record_type', 'record_id'));
Utils_RecordBrowserCommon::add_access('premium_multiple_addresses', 'add', 'EMPLOYEE', array('nickname'=>''));
Utils_RecordBrowserCommon::add_access('premium_multiple_addresses', 'edit', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_multiple_addresses', 'delete', 'EMPLOYEE');

Utils_RecordBrowserCommon::add_access('premium_payments_agent', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_payments_agent', 'add', 'ADMIN');
Utils_RecordBrowserCommon::add_access('premium_payments_agent', 'edit', 'ADMIN', array(), array('last_update', 'type'));
Utils_RecordBrowserCommon::add_access('premium_payments_agent', 'delete', 'ADMIN');

Utils_RecordBrowserCommon::add_access('premium_payments', 'view', 'EMPLOYEE', array('type'=>1), array('check_date', 'cvc_cvv','record_type','record_id', 'type'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'view', array('EMPLOYEE','ACCESS:manager'), array('type'=>1), array('check_date','record_type','record_id', 'type'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'view', 'EMPLOYEE', array('(type'=>2,'|type'=>0), array('expiration_date', 'cvc_cvv','record_type','record_id', 'type'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'view', 'EMPLOYEE', array('type'=>''), array('card_number','expiration_date','cvc_cvv','check_date','record_type','record_id', 'type'));

Utils_RecordBrowserCommon::add_access('premium_payments', 'add', 'EMPLOYEE', array('amount'=>'','type'=>1), array('record_type','record_id','status','status_description','type','check_date'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'add', 'EMPLOYEE', array('amount'=>'','(type'=>2,'|type'=>0), array('record_type','record_id','status','status_description','expiration_date','cvc_cvv','type'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'add', 'EMPLOYEE', array('amount'=>'','type'=>''), array('record_type','record_id','card_number','expiration_date','cvc_cvv','status','status_description','type','check_date'));

Utils_RecordBrowserCommon::add_access('premium_payments', 'edit', array('EMPLOYEE','ACCESS:manager'), array('<status'=>2,'type'=>1), array('record_type','record_id','status_description','type','check_date'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'edit', array('EMPLOYEE','ACCESS:manager'), array('<status'=>2,'(type'=>2,'|type'=>0), array('record_type','record_id','status_description','type','expiration_date','cvc_cvv'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'edit', array('EMPLOYEE','ACCESS:manager'), array('<status'=>2,'type'=>''), array('record_type','record_id','status_description','type','card_number','expiration_date','cvc_cvv','check_date'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'edit', 'ADMIN', array('type'=>1), array('record_type','record_id','status_description','type','check_date'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'edit', 'ADMIN', array('(type'=>2,'|type'=>0), array('record_type','record_id','status_description','type','expiration_date','cvc_cvv'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'edit', 'ADMIN', array('type'=>''), array('record_type','record_id','status_description','type','card_number','expiration_date','cvc_cvv','check_date'));

Utils_RecordBrowserCommon::add_access('premium_payments', 'delete', array('EMPLOYEE','ACCESS:manager'), array('<status'=>2));
Utils_RecordBrowserCommon::add_access('premium_payments', 'delete', 'ADMIN');

Utils_RecordBrowserCommon::add_access('relations_types', 'view', 'EMPLOYEE', array(), array('type_name'));
Utils_RecordBrowserCommon::add_access('relations_types', 'add', 'EMPLOYEE', array(), array('type_name'));
Utils_RecordBrowserCommon::add_access('relations_types', 'edit', 'EMPLOYEE', array(), array('type_name'));
Utils_RecordBrowserCommon::add_access('relations_types', 'delete', 'EMPLOYEE', array('type_name'=>''));

Utils_RecordBrowserCommon::add_default_access('relations');

Utils_RecordBrowserCommon::add_access('premium_roundcube_custom_addon', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_roundcube_custom_addon', 'add', 'ADMIN');
Utils_RecordBrowserCommon::add_access('premium_roundcube_custom_addon', 'edit', 'SUPERADMIN');
Utils_RecordBrowserCommon::add_access('premium_roundcube_custom_addon', 'delete', 'SUPERADMIN');

Utils_RecordBrowserCommon::add_default_access('premium_salesopportunity');

Utils_RecordBrowserCommon::add_default_access('premium_schoolregister_course');
Utils_RecordBrowserCommon::add_default_access('premium_schoolregister_room');
Utils_RecordBrowserCommon::add_default_access('premium_schoolregister_attendance');

Utils_RecordBrowserCommon::add_access('premium_schoolregister_student_group', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_student_group', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_student_group', 'edit', 'EMPLOYEE', array(), array('students', 'course'));
Utils_RecordBrowserCommon::add_access('premium_schoolregister_student_group', 'delete', 'EMPLOYEE');

// shouldn't be done with access
Utils_RecordBrowserCommon::add_access('premium_schoolregister_schedule', 'view', 'EMPLOYEE', array('special_education'=>1, 'custom_course'=>''), array('term', 'custom_course'));
Utils_RecordBrowserCommon::add_access('premium_schoolregister_schedule', 'view', 'EMPLOYEE', array('special_education'=>'', 'custom_course'=>''), array('start_date', 'end_date', 'custom_course'));
Utils_RecordBrowserCommon::add_access('premium_schoolregister_schedule', 'view', 'EMPLOYEE', array('special_education'=>1, 'custom_course'=>1), array('term', 'course'));
Utils_RecordBrowserCommon::add_access('premium_schoolregister_schedule', 'view', 'EMPLOYEE', array('special_education'=>'', 'custom_course'=>1), array('start_date', 'end_date', 'course'));
Utils_RecordBrowserCommon::add_access('premium_schoolregister_schedule', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_schedule', 'edit', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_schedule', 'delete', 'EMPLOYEE');

// shouldn't be done with access
Utils_RecordBrowserCommon::add_access('premium_schoolregister_lesson', 'view', 'EMPLOYEE', array('custom_course'=>1), array('students', 'special_education', 'course'));
Utils_RecordBrowserCommon::add_access('premium_schoolregister_lesson', 'view', 'EMPLOYEE', array('custom_course'=>''), array('students', 'special_education', 'custom_course'));
Utils_RecordBrowserCommon::add_access('premium_schoolregister_lesson', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_lesson', 'edit', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_lesson', 'delete', 'EMPLOYEE');

Utils_RecordBrowserCommon::add_access('premium_schoolregister_att_event_type', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_att_event_type', 'add', 'SUPERADMIN');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_att_event_type', 'edit', 'SUPERADMIN');

Utils_RecordBrowserCommon::add_access('premium_schoolregister_term', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_term', 'add', 'SUPERADMIN');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_term', 'edit', 'SUPERADMIN');

Utils_RecordBrowserCommon::add_access('contact_skills', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('contact_skills', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('contact_skills', 'edit', 'EMPLOYEE', array(), array('contact'));
Utils_RecordBrowserCommon::add_access('contact_skills', 'delete', 'EMPLOYEE');

Utils_RecordBrowserCommon::add_access('premium_timesheet', 'view', array('EMPLOYEE','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('premium_timesheet', 'add', array('EMPLOYEE','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('premium_timesheet', 'edit', array('EMPLOYEE','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('premium_timesheet', 'delete', array('EMPLOYEE','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('premium_timesheet', 'view', 'EMPLOYEE', array('employee'=>'USER'));
Utils_RecordBrowserCommon::add_access('premium_timesheet', 'view', 'EMPLOYEE', array(), array('billing_rate'));
Utils_RecordBrowserCommon::add_access('premium_timesheet', 'add', 'EMPLOYEE', array('employee'=>'USER'), array('employee'));
Utils_RecordBrowserCommon::add_access('premium_timesheet', 'edit', 'EMPLOYEE', array('employee'=>'USER'), array('employee'));
Utils_RecordBrowserCommon::add_access('premium_timesheet', 'delete', 'EMPLOYEE', array('employee'=>'USER'));

Utils_RecordBrowserCommon::add_default_access('premium_timesheet_rate');

Utils_RecordBrowserCommon::add_access('premium_ecommerce_products', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_ecommerce_products', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_ecommerce_products', 'edit', 'EMPLOYEE', array(), array('currency','gross_price'));
Utils_RecordBrowserCommon::add_access('premium_ecommerce_products', 'delete', 'EMPLOYEE');

Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_emails');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_3rdp_info');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_cat_descriptions');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_descriptions');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_parameters');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_parameter_labels');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_parameter_groups');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_param_group_labels');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_products_parameters');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_availability');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_availability_labels');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_pages');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_pages_data');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_prices');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_payments_carriers');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_polls');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_poll_answers');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_boxes');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_promotion_codes');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_banners');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_product_comments');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_orders');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_users');
Utils_RecordBrowserCommon::add_default_access('premium_ecommerce_newsletter');

Utils_RecordBrowserCommon::add_access('premium_warehouse_items', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_warehouse_items', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_warehouse_items', 'edit', 'EMPLOYEE', array(), array('item_type'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items', 'delete', array('EMPLOYEE', 'ACCESS:manager'));

Utils_RecordBrowserCommon::add_default_access('premium_warehouse_items_categories');

Utils_RecordBrowserCommon::add_access('premium_warehouse_location', 'view', 'EMPLOYEE');

Utils_RecordBrowserCommon::add_access('premium_warehouse', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_warehouse', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_warehouse', 'edit', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_warehouse', 'delete', array('EMPLOYEE', 'ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('premium_warehouse_distributor', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_warehouse_distributor', 'add', 'EMPLOYEE', array(), array('last_update'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_distributor', 'edit', 'EMPLOYEE', array(), array('last_update'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_distributor', 'delete', array('EMPLOYEE', 'ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('premium_warehouse_distributor', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_warehouse_distributor', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_warehouse_distributor', 'edit', 'EMPLOYEE', array(), array('last_update'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_distributor', 'delete', array('EMPLOYEE', 'ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('premium_weblink_custom_addon', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_weblink_custom_addon', 'add', 'ADMIN', array('recordset'=>''));
Utils_RecordBrowserCommon::add_access('premium_weblink_custom_addon', 'edit', 'SUPERADMIN');
Utils_RecordBrowserCommon::add_access('premium_weblink_custom_addon', 'delete', 'SUPERADMIN');

Utils_RecordBrowserCommon::add_access('premium_weblink', 'view', 'EMPLOYEE', array());
Utils_RecordBrowserCommon::add_access('premium_weblink', 'add', 'EMPLOYEE', array('link'=>''));
Utils_RecordBrowserCommon::add_access('premium_weblink', 'edit', 'EMPLOYEE', array(), array('date'));
Utils_RecordBrowserCommon::add_access('premium_weblink', 'delete', 'EMPLOYEE');

if(ModuleManager::is_installed('Premium_Weblink')>=0) {
	DB::Execute('UPDATE premium_weblink_field SET type=%s WHERE field=%s', array('hidden', 'Record Type'));
}

Utils_RecordBrowserCommon::add_access('bugtrack', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('bugtrack', 'add', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('bugtrack', 'edit', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('bugtrack', 'delete', array('EMPLOYEE', 'ACCESS:manager'));

if(ModuleManager::is_installed('Custom_Prosperix_AccessRestrictions')>=0) {
	ModuleManager::include_install('Custom_Prosperix_AccessRestrictions');
	$m = new Custom_Prosperix_AccessRestrictionsInstall();
	$m->install();
	$ret = DB::Execute('SELECT * FROM user_login');
	while ($row = $ret->FetchRow()) {
		$aid = Base_AclCommon::get_acl_user_id($row['id']);
		$allow = Base_AclCommon::is_user_in_group($aid, 'Employee Vendor Moderator');
		$allow |= Base_AclCommon::is_user_in_group($aid, 'Administrator');
		$allow |= Base_AclCommon::is_user_in_group($aid, 'Super administrator');
		if ($allow) {
			$r2 = DB::Execute('SELECT * FROM contact_data_1 WHERE f_login=%d', array($row['id']));
			while ($r=$r2->FetchRow()) {
				if (!$r['f_access']) $gr = '__vendor_manager__';
				else $gr = $r['f_access'].'vendor_manager__';
				DB::Execute('UPDATE contact_data_1 SET f_access=%s WHERE id=%d', array($gr, $r['id']));
			}
		}
	}
}

if(ModuleManager::is_installed('Custom_CADES_AccessRestrictions')>=0) {
	DB::Execute('UPDATE cades_behavior_log_field SET type="select", param="contact::Last Name|First Name;" WHERE field="Person"');
	Utils_RecordBrowserCommon::set_QFfield_callback('cades_behavior_log', 'Person', array('Custom_CADES_BehaviorCommon', 'QFfield_log_person'));

	Utils_CommonDataCommon::extend_array('Contacts/Access',array('mrm'=>'Medical Record Manager'));
	$ret = DB::Execute('SELECT * FROM user_login');
	while ($row = $ret->FetchRow()) {
		$aid = Base_AclCommon::get_acl_user_id($row['id']);
		$allow = Base_AclCommon::is_user_in_group($aid, 'Medical Record Manager');
		$allow |= Base_AclCommon::is_user_in_group($aid, 'Administrator');
		$allow |= Base_AclCommon::is_user_in_group($aid, 'Super administrator');
		if ($allow) {
			$r2 = DB::Execute('SELECT * FROM contact_data_1 WHERE f_login=%d', array($row['id']));
			while ($r=$r2->FetchRow()) {
				if (!$r['f_access']) $gr = '__mrm__';
				else $gr = $r['f_access'].'mrm__';
				DB::Execute('UPDATE contact_data_1 SET f_access=%s WHERE id=%d', array($gr, $r['id']));
			}
		}
	}
	Acl::del_group('Medical Record Manager');

	Utils_RecordBrowserCommon::new_record_field('contact', array('name'=>'View', 'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('Custom_CADES_AccessRestrictionsCommon', 'employee_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>false, 'extra'=>true, 'filter'=>false, 'visible'=>false));
	Utils_RecordBrowserCommon::new_record_field('contact', array('name'=>'Edit', 'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('Custom_CADES_AccessRestrictionsCommon', 'employee_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>false, 'extra'=>true, 'filter'=>false, 'visible'=>false));
	Utils_RecordBrowserCommon::new_record_field('contact', array('name'=>'Add', 'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('Custom_CADES_AccessRestrictionsCommon', 'employee_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>false, 'extra'=>true, 'filter'=>false, 'visible'=>false));
	Utils_RecordBrowserCommon::new_record_field('contact', array('name'=>'Delete', 'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('Custom_CADES_AccessRestrictionsCommon', 'employee_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>false, 'extra'=>true, 'filter'=>false, 'visible'=>false));

	// migrate data
	if (Utils_RecordBrowserCommon::check_table_name('cades_access_control', false, false)) {
		$recs = DB::Execute('SELECT * FROM cades_access_control_data_1 WHERE active=1');
		$perms = array();
		$rm_acl = array();
		$count = 0;
		$done = 0;
		while ($r = $recs->FetchRow()) {
			if (!isset($rm_acl[$r['f_patient']])) $rm_acl[$r['f_patient']] = array();
			$rm_acl[$r['f_patient']][] = $r['id'];
			$field = Utils_RecordBrowserCommon::get_field_id(Utils_CommonDataCommon::get_value('CADES/AccessLevel/'.$r['f_permission_level']));
			$fields = explode('_', $field);
			foreach ($fields as $field) {
				if (!isset($perms[$r['f_patient']][$field])) $perms[$r['f_patient']][$field] = array();
				$perms[$r['f_patient']][$field][] = $r['f_employee'];
			}
		}
		print('Patients left: '.count($perms).'<br>');
		foreach ($perms as $patient=>$v) {
			print('Patient '.$patient);
			Utils_RecordBrowserCommon::update_record('contact', $patient, $v);
			print('... cleanup... ');
			DB::StartTrans();
			foreach ($rm_acl[$patient] as $i)
				DB::Execute('UPDATE cades_access_control_data_1 SET active=0 WHERE id=%d', array($i));
			DB::CompleteTrans();
			print('done<br>');
		}
	}
	
	Utils_RecordBrowserCommon::wipe_access('contact');
	Utils_RecordBrowserCommon::add_access('contact', 'view', 'EMPLOYEE', array('(!permission'=>2, '|:Created_by'=>'USER_ID'), array('birth_date','ssn','home_phone','home_address_1','home_address_2','home_city','home_country','home_zone','home_postal_code', 'view', 'edit', 'add', 'delete'));
	Utils_RecordBrowserCommon::add_access('contact', 'view', 'ALL', array('login'=>'USER_ID'), array('view', 'edit', 'add', 'delete'));
	Utils_RecordBrowserCommon::add_access('contact', 'view', array('EMPLOYEE','ACCESS:mrm'), array('(!permission'=>2, '|:Created_by'=>'USER_ID'), array('view', 'edit', 'add', 'delete'));
	Utils_RecordBrowserCommon::add_access('contact', 'add', array('EMPLOYEE','ACCESS:manager'));
	Utils_RecordBrowserCommon::add_access('contact', 'edit', 'EMPLOYEE', array('(permission'=>0, '|:Created_by'=>'USER_ID', '!group'=>array('patient', 'ex_patient')), array('access', 'login'));
	Utils_RecordBrowserCommon::add_access('contact', 'edit', 'ALL', array('login'=>'USER_ID'), array('access', 'login'));
	Utils_RecordBrowserCommon::add_access('contact', 'edit', array('EMPLOYEE','ACCESS:mrm'), array());
	Utils_RecordBrowserCommon::add_access('contact', 'delete', array('EMPLOYEE','ACCESS:mrm'));

	Utils_RecordBrowserCommon::add_access('contact', 'view', 'ALL', array('view'=>'USER'), array('view', 'edit', 'add', 'delete'));
	Utils_RecordBrowserCommon::add_access('contact', 'edit', 'ALL', array('edit'=>'USER'));
	Utils_RecordBrowserCommon::add_access('contact', 'delete', 'ALL', array('delete'=>'USER'));

	Utils_RecordBrowserCommon::uninstall_recordset('cades_access_control');
	Utils_CommonDataCommon::remove('CADES/AccessLevel');

	Custom_CADES_AccessRestrictionsCommon::add_default_cades_permissions('cades_appointments');
	Custom_CADES_AccessRestrictionsCommon::add_default_cades_permissions('cades_allergies');
	Custom_CADES_AccessRestrictionsCommon::add_default_cades_permissions('cades_behavior');
	Custom_CADES_AccessRestrictionsCommon::add_default_cades_permissions('cades_behavior_log');
	Custom_CADES_AccessRestrictionsCommon::add_default_cades_permissions('cades_diagnosis', 'patient');
	Custom_CADES_AccessRestrictionsCommon::add_default_cades_permissions('cades_diet');
	Custom_CADES_AccessRestrictionsCommon::add_default_cades_permissions('cades_hospitalizations');
	Custom_CADES_AccessRestrictionsCommon::add_default_cades_permissions('cades_immunizations');
	Custom_CADES_AccessRestrictionsCommon::add_default_cades_permissions('cades_insurance');
	Custom_CADES_AccessRestrictionsCommon::add_default_cades_permissions('cades_issues', 'patient');
	Custom_CADES_AccessRestrictionsCommon::add_default_cades_permissions('cades_medicaltests');
	Custom_CADES_AccessRestrictionsCommon::add_default_cades_permissions('cades_medications');
	Custom_CADES_AccessRestrictionsCommon::add_default_cades_permissions('cades_reviews');
	Custom_CADES_AccessRestrictionsCommon::add_default_cades_permissions('cades_seizures');
	Custom_CADES_AccessRestrictionsCommon::add_default_cades_permissions('cades_services');
	Custom_CADES_AccessRestrictionsCommon::add_default_cades_permissions('cades_sleep');
	Custom_CADES_AccessRestrictionsCommon::add_default_cades_permissions('cades_toileting');
	Custom_CADES_AccessRestrictionsCommon::add_default_cades_permissions('cades_vitalsigns');

	Utils_RecordBrowserCommon::add_access('cades_incidents', 'view', array('EMPLOYEE','ACCESS:mrm'));
	Utils_RecordBrowserCommon::add_access('cades_incidents', 'edit', array('EMPLOYEE','ACCESS:mrm'));
	Utils_RecordBrowserCommon::add_access('cades_incidents', 'add', array('EMPLOYEE','ACCESS:mrm'));
	Utils_RecordBrowserCommon::add_access('cades_incidents', 'delete', array('EMPLOYEE','ACCESS:mrm'));
	
	$field = 'person';
	Utils_RecordBrowserCommon::add_access('cades_incidents', 'view', 'ALL', array($field.'[view]'=>'USER','(employees'=>'USER', '|employees'=>''));
	Utils_RecordBrowserCommon::add_access('cades_incidents', 'view', 'ALL', array($field.'[view]'=>'USER'), array('employees', 'notes', 'description', 'witness', 'notified_party', 'action_taken'));
	Utils_RecordBrowserCommon::add_access('cades_incidents', 'edit', 'ALL', array($field.'[edit]'=>'USER'), array($field));
	Utils_RecordBrowserCommon::add_access('cades_incidents', 'add', 'ALL', array('('.$field=>'','|'.$field.'[add]'=>'USER'));
	Utils_RecordBrowserCommon::add_access('cades_incidents', 'delete', 'ALL', array($field.'[delete]'=>'USER'));

	Utils_RecordBrowserCommon::add_default_access('cades_billing_authorization');
	Utils_RecordBrowserCommon::add_default_access('cades_billing_auth_used');
	Utils_RecordBrowserCommon::add_default_access('premium_schoolregister_att_except');
	Utils_RecordBrowserCommon::add_default_access('cades_billing_auth_rejected');
	Utils_RecordBrowserCommon::add_default_access('cades_billing_auth_bill');
	
	DB::CreateIndex('prem_school_reg__idx_1', 'premium_schoolregister_lesson_data_1', 'f_date');
	DB::CreateIndex('prem_school_reg__idx_2', 'premium_schoolregister_lesson_data_1', 'f_course');
	DB::CreateIndex('prem_school_reg__idx_3', 'premium_schoolregister_lesson_data_1', 'f_custom_course');

	DB::Execute('UPDATE contact_data_1 SET f_company_name=1, f_related_companies=NULL WHERE f_related_companies=%s AND f_company_name IS NULL', array('__1__'));
	

}

if(ModuleManager::is_installed('Premium_Warehouse_eCommerce')>=0) {
	DB::Execute('UPDATE premium_ecommerce_products_field SET type=%s WHERE field=%s OR field=%s', array(Variable::get('ecommerce_item_descriptions')?'calculated':'hidden', 'Product Name', 'Description'));
	foreach (array ('premium_ecommerce_products',
	'premium_ecommerce_parameters',
	'premium_ecommerce_parameter_groups',
	'premium_ecommerce_pages',
	'premium_ecommerce_polls',
	'premium_ecommerce_boxes',
	'premium_ecommerce_3rdp_info') as $t) {
		DB::Execute('UPDATE '.$t.'_field SET type=%s WHERE field=%s', array('hidden', 'Position'));
	}
}

if(ModuleManager::is_installed('Premium_Warehouse_Items')>=0) {
	DB::Execute('UPDATE premium_warehouse_items_categories_field SET type=%s WHERE field=%s', array('hidden', 'Position'));
}


if(ModuleManager::is_installed('Premium_Warehouse_Items_Orders')>=0) {
	Utils_RecordBrowserCommon::field_deny_access('premium_warehouse_items', 'Quantity on Hand', 'edit');
}

Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders', 'view', 'ALL', array('contact'=>'USER'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders', 'view', array('ALL','ACCESS:manager'), array('company'=>'USER_COMPANY'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders', 'add', 'EMPLOYEE', array(), array('transaction_type'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders', 'edit', 'EMPLOYEE', array('employee'=>'USER', '(>=transaction_date'=>'-7 days', '|<status'=>20), array('transaction_type', 'warehouse'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders', 'edit', 'EMPLOYEE', array('employee'=>'USER', 'warehouse'=>''), array('transaction_type'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders', 'edit', array('EMPLOYEE','ACCESS:manager'), array(), array('transaction_type', 'warehouse'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders', 'delete', 'EMPLOYEE', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders', 'delete', array('EMPLOYEE','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders_details', 'view', 'EMPLOYEE');
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders_details', 'view', 'ALL', array('transaction_id[contact]'=>'USER'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders_details', 'view', array('ALL','ACCESS:manager'), array('transaction_id[company]'=>'USER_COMPANY'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders_details', 'add', 'EMPLOYEE', array('transaction_id[employee]'=>'USER', '(>=transaction_id[transaction_date]'=>'-7 days', '|<transaction_id[status]'=>20), array('transaction_id'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders_details', 'add', array('EMPLOYEE','ACCESS:manager'), array(), array('transaction_id'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders_details', 'edit', 'EMPLOYEE', array('transaction_id[employee]'=>'USER', '(>=transaction_id[transaction_date]'=>'-7 days', '|<transaction_id[status]'=>20), array('transaction_id'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders_details', 'edit', array('EMPLOYEE','ACCESS:manager'), array(), array('transaction_id'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders_details', 'delete', 'EMPLOYEE', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders_details', 'delete', array('EMPLOYEE','ACCESS:manager'));


?>