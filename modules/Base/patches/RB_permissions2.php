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

Utils_RecordBrowserCommon::new_record_field('contact', array('name' => _M('Login Panel'),		'type'=>'page_split', 'param'=>1));

$fields = array(
	array('name' => _M('Username'), 		'type'=>'calculated', 'required'=>false, 'extra'=>true, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_username')),
	array('name' => _M('Set Password'), 	'type'=>'calculated', 'required'=>false, 'extra'=>true, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_password')),
	array('name' => _M('Confirm Password'),'type'=>'calculated', 'required'=>false, 'extra'=>true, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_repassword')),
	array('name' => _M('Admin'), 			'type'=>'calculated', 'required'=>false, 'extra'=>true, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_admin')),
	array('name' => _M('Access'), 		'type'=>'multiselect', 'required'=>false, 'param'=>Utils_RecordBrowserCommon::multiselect_from_common('Contacts/Access'), 'extra'=>true, 'QFfield_callback'=>array('CRM_ContactsCommon', 'QFfield_access'))
);

foreach ($fields as $f)
	Utils_RecordBrowserCommon::new_record_field('contact', $f);

$pos = DB::GetOne('SELECT position FROM contact_field WHERE field=%s', array('Login'));
$pos2 = DB::GetOne('SELECT position FROM contact_field WHERE field=%s', array('Username'));
DB::Execute('UPDATE contact_field SET position=position-1 WHERE position>%d AND position<%d', array($pos, $pos2));
DB::Execute('UPDATE contact_field SET position=%d, style=%s WHERE field=%s', array($pos2-1,'','Login'));

Utils_CommonDataCommon::extend_array('Contacts/Access',array('manager'=>_M('Manager')));

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

PatchUtil::db_drop_column('recordbrowser_table_properties','access_callback');


Utils_RecordBrowserCommon::add_default_access('crm_assets');

Utils_RecordBrowserCommon::add_access('company', 'view', 'ACCESS:employee', array('(!permission'=>2, '|:Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('company', 'view', 'ALL', array('id'=>'USER_COMPANY'));
Utils_RecordBrowserCommon::add_access('company', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('company', 'edit', 'ACCESS:employee', array('(permission'=>0, '|:Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('company', 'edit', array('ALL','ACCESS:manager'), array('id'=>'USER_COMPANY'));
Utils_RecordBrowserCommon::add_access('company', 'edit', array('ACCESS:employee','ACCESS:manager'), array());
Utils_RecordBrowserCommon::add_access('company', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('company', 'delete', array('ACCESS:employee','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('contact', 'view', 'ACCESS:employee', array('(!permission'=>2, '|:Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('contact', 'view', 'ALL', array('login'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('contact', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('contact', 'edit', 'ACCESS:employee', array('(permission'=>0, '|:Created_by'=>'USER_ID'), array('access', 'login'));
Utils_RecordBrowserCommon::add_access('contact', 'edit', 'ALL', array('login'=>'USER_ID'), array('company_name', 'related_companies', 'access', 'login'));
Utils_RecordBrowserCommon::add_access('contact', 'edit', array('ALL','ACCESS:manager'), array('company_name'=>'USER_COMPANY'), array('login', 'company_name', 'related_companies'));
Utils_RecordBrowserCommon::add_access('contact', 'edit', array('ACCESS:employee','ACCESS:manager'), array());
Utils_RecordBrowserCommon::add_access('contact', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('contact', 'delete', array('ACCESS:employee','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('crm_meeting', 'view', 'ACCESS:employee', array('(!permission'=>2, '|employees'=>'USER'));
Utils_RecordBrowserCommon::add_access('crm_meeting', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('crm_meeting', 'edit', 'ACCESS:employee', array('(permission'=>0, '|employees'=>'USER', '|customers'=>'USER'));
Utils_RecordBrowserCommon::add_access('crm_meeting', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('crm_meeting', 'delete', array('ACCESS:employee','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('phonecall', 'view', 'ACCESS:employee', array('(!permission'=>2, '|employees'=>'USER'));
Utils_RecordBrowserCommon::add_access('phonecall', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('phonecall', 'edit', 'ACCESS:employee', array('(permission'=>0, '|employees'=>'USER', '|customer'=>'USER'));
Utils_RecordBrowserCommon::add_access('phonecall', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('phonecall', 'delete', array('ACCESS:employee','ACCESS:manager'));

if(ModuleManager::is_installed('CRM_Roundcube')>=0) {
	Utils_RecordBrowserCommon::add_access('rc_accounts', 'view', 'ACCESS:employee', array('epesi_user'=>'USER_ID'));
	Utils_RecordBrowserCommon::add_access('rc_accounts', 'add', 'ACCESS:employee');
	Utils_RecordBrowserCommon::add_access('rc_accounts', 'edit', 'ACCESS:employee', array(), array('epesi_user'));
	Utils_RecordBrowserCommon::add_access('rc_accounts', 'delete', 'ACCESS:employee', array('epesi_user'=>'USER_ID'));

	Utils_RecordBrowserCommon::add_access('rc_mails', 'view', 'ACCESS:employee', array(), array('headers_data'));
	Utils_RecordBrowserCommon::add_access('rc_mails', 'delete', 'ACCESS:employee');

	Utils_RecordBrowserCommon::add_access('rc_mails_assoc', 'view', 'ACCESS:employee', array(), array('recordset'));
	Utils_RecordBrowserCommon::add_access('rc_mails_assoc', 'delete', 'ACCESS:employee');

	Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'view', 'ACCESS:employee');
	Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'add', 'ACCESS:employee');
	Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'edit', 'ACCESS:employee');
	Utils_RecordBrowserCommon::add_access('rc_multiple_emails', 'delete', 'ACCESS:employee');

	DB::Execute('UPDATE rc_multiple_emails_field SET type=%s WHERE field=%s OR field=%s', array('hidden', 'Record Type', 'Record ID'));
}

Utils_RecordBrowserCommon::add_access('task', 'view', 'ACCESS:employee', array('(!permission'=>2, '|employees'=>'USER'));
Utils_RecordBrowserCommon::add_access('task', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('task', 'edit', 'ACCESS:employee', array('(permission'=>0, '|employees'=>'USER', '|customers'=>'USER'));
Utils_RecordBrowserCommon::add_access('task', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('task', 'delete', array('ACCESS:employee','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('premium_projects', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_projects', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_projects', 'edit', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_projects', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('premium_projects', 'delete', array('ACCESS:employee','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('premium_tickets_testing', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_tickets_testing', 'add', 'ACCESS:employee', array('!ticket[status]'=>6), array('finished_on'));
Utils_RecordBrowserCommon::add_access('premium_tickets_testing', 'edit', 'ACCESS:employee', array(), array('finished_on', 'ticket'));
Utils_RecordBrowserCommon::add_access('premium_tickets_testing', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('premium_tickets_testing', 'delete', array('ACCESS:employee','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('premium_tickets', 'view', 'ACCESS:employee', array('(!permission'=>2, '|assigned_to'=>'USER', '|ticket_owner'=>'USER'));
Utils_RecordBrowserCommon::add_access('premium_tickets', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_tickets', 'edit', 'ACCESS:employee', array('ticket_owner'=>'USER'));
Utils_RecordBrowserCommon::add_access('premium_tickets', 'edit', 'ACCESS:employee', array('assigned_to'=>'USER'), array('ticket_owner', 'status'));
Utils_RecordBrowserCommon::add_access('premium_tickets', 'edit', 'ACCESS:employee', array('status'=>0, 'permission'=>0), array('ticket_owner', 'status'));
Utils_RecordBrowserCommon::add_access('premium_tickets', 'edit', array('ACCESS:employee','ACCESS:manager'), array('(permission'=>0,'|assigned_to'=>'USER'));
Utils_RecordBrowserCommon::add_access('premium_tickets', 'delete', 'ACCESS:employee', array('ticket_owner'=>'USER'));

if(ModuleManager::is_installed('Premium_Projects_Tickets_Testing')>=0) {
	Utils_RecordBrowserCommon::field_deny_access('premium_tickets', 'Tested', 'edit');
	Utils_RecordBrowserCommon::field_deny_access('premium_tickets', 'Tested', 'add');
}


/** PREMIUM **/

Utils_RecordBrowserCommon::add_default_access('custom_jobsearch_advertisinglog');
Utils_RecordBrowserCommon::add_default_access('custom_jobsearch_advertisinglog_cost');

Utils_RecordBrowserCommon::add_access('custom_jobsearch_advertisinglog_element', 'view', 'ACCESS:employee', array(), array('record_id'));
Utils_RecordBrowserCommon::add_access('custom_jobsearch_advertisinglog_element', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_jobsearch_advertisinglog_element', 'edit', 'ACCESS:employee', array(), array('record_type', 'list_id'));

Utils_RecordBrowserCommon::add_default_access('custom_jobsearch_advertisinglog_history');

Utils_RecordBrowserCommon::add_default_access('custom_jobsearch');

Utils_RecordBrowserCommon::add_access('custom_jobsearch_element', 'view', 'ACCESS:employee', array(), array('record_id'));
Utils_RecordBrowserCommon::add_access('custom_jobsearch_element', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_jobsearch_element', 'edit', 'ACCESS:employee', array(), array('list_id'));

Utils_RecordBrowserCommon::add_access('custom_jobsearch_history', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_jobsearch_history', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_jobsearch_history', 'edit', 'ACCESS:employee', array(), array('target', 'list_id'));
Utils_RecordBrowserCommon::add_access('custom_jobsearch_history', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('custom_jobsearch_history', 'delete', array('ACCESS:employee','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('custom_merlin_licence', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_merlin_licence', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_merlin_licence', 'edit', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_merlin_licence', 'delete', array('ACCESS:employee','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('custom_merlin_charge', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_merlin_charge', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_merlin_charge', 'edit', 'ACCESS:employee', array(), array('licence'));
Utils_RecordBrowserCommon::add_access('custom_merlin_charge', 'delete', array('ACCESS:employee','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('custom_merlin_charge', 'view', 'ACCESS:employee');

Utils_RecordBrowserCommon::add_default_access('custom_monthlycost');

Utils_RecordBrowserCommon::add_default_access('custom_personalequipment_disbursement');
Utils_RecordBrowserCommon::add_default_access('custom_personalequipment_disbur_items');

Utils_RecordBrowserCommon::add_default_access('custom_personalequipment');

Utils_RecordBrowserCommon::add_default_access('custom_changeorders');

Utils_RecordBrowserCommon::add_default_access('custom_equipment');

Utils_RecordBrowserCommon::add_access('custom_projects_progbilling', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_projects_progbilling', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_projects_progbilling', 'edit', 'ACCESS:employee', array(), array('project_name'));
Utils_RecordBrowserCommon::add_access('custom_projects_progbilling', 'delete', 'ACCESS:employee');

Utils_RecordBrowserCommon::add_access('custom_projects', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_projects', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_projects', 'edit', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_projects', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('custom_projects', 'delete', array('ACCESS:employee','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('custom_salesgoals', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_salesgoals', 'add', array('ACCESS:employee','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('custom_salesgoals', 'edit', array('ACCESS:employee','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('custom_salesgoals', 'delete', array('ACCESS:employee','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('custom_shopequipment', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_shopequipment', 'add', 'ACCESS:employee', array(), array('job','units_used','total_units_used','rental_date','est_ret_date','last_history_entry'));
Utils_RecordBrowserCommon::add_access('custom_shopequipment', 'edit', 'ACCESS:employee', array(), array('job','units_used','total_units_used','rental_date','est_ret_date','last_history_entry'));
Utils_RecordBrowserCommon::add_access('custom_shopequipment', 'delete', 'ACCESS:employee');

Utils_RecordBrowserCommon::add_access('custom_shopequipment_rental', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_shopequipment_rental', 'add', 'ACCESS:employee', array(), array('return_date'));
Utils_RecordBrowserCommon::add_access('custom_shopequipment_rental', 'edit', 'ACCESS:employee', array(), array('return_date', 'equipment_id'));
Utils_RecordBrowserCommon::add_access('custom_shopequipment_rental', 'delete', 'ACCESS:employee');

Utils_RecordBrowserCommon::add_access('custom_tickets', 'view', 'ACCESS:employee', array('(!permission'=>2, '|assigned_to'=>'USER', '|:Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('custom_tickets', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_tickets', 'edit', 'ACCESS:employee', array('(permission'=>0, '|assigned_to'=>'USER', '|:Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('custom_tickets', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));

Utils_RecordBrowserCommon::add_access('custom_projects_visit', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_projects_visit', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('custom_projects_visit', 'edit', 'ACCESS:employee');

Utils_RecordBrowserCommon::add_access('data_tax_rates', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('data_tax_rates', 'add', array('ACCESS:employee','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('data_tax_rates', 'edit', array('ACCESS:employee','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('data_tax_rates', 'delete', array('ACCESS:employee','ACCESS:manager'));

Utils_RecordBrowserCommon::add_default_access('premium_apartments_maintenance');
Utils_RecordBrowserCommon::add_default_access('premium_apartments_agent');
Utils_RecordBrowserCommon::add_default_access('premium_apartments_apartment');

Utils_RecordBrowserCommon::add_access('premium_apartments_rental', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_apartments_rental', 'add', 'ACCESS:employee', array(), array('status'));
Utils_RecordBrowserCommon::add_access('premium_apartments_rental', 'edit', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_apartments_rental', 'delete', 'ACCESS:employee');

Utils_RecordBrowserCommon::add_access('premium_apartments_billing', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_apartments_billing', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_apartments_billing', 'edit', 'ACCESS:employee', array(), array('rental'));
Utils_RecordBrowserCommon::add_access('premium_apartments_billing', 'delete', 'ACCESS:employee');

Utils_RecordBrowserCommon::add_access('premium_apartments_change_order', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_apartments_change_order', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_apartments_change_order', 'edit', 'ACCESS:employee', array(), array('rental'));
Utils_RecordBrowserCommon::add_access('premium_apartments_change_order', 'delete', 'ACCESS:employee');

Utils_RecordBrowserCommon::add_access('premium_campaignmanager_messages', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_campaignmanager_messages', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_campaignmanager_messages', 'edit', 'ACCESS:employee', array(), array('list'));
Utils_RecordBrowserCommon::add_access('premium_campaignmanager_messages', 'delete', 'ACCESS:employee');

Utils_RecordBrowserCommon::add_access('premium_checklist_list_entry', 'view', 'ACCESS:employee', array(), array('completed'));
Utils_RecordBrowserCommon::add_access('premium_checklist_list_entry', 'add', 'ACCESS:employee', array(), array('next_date'));
Utils_RecordBrowserCommon::add_access('premium_checklist_list_entry', 'edit', array('ACCESS:employee','ACCESS:manager'), array(), array('checklist'));
Utils_RecordBrowserCommon::add_access('premium_checklist_list_entry', 'edit', 'ACCESS:employee', array('completed'=>'', 'employee'=>'USER'), array('employee', 'checklist'));
Utils_RecordBrowserCommon::add_access('premium_checklist_list_entry', 'delete', array('ACCESS:employee','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('premium_checklist_list_entry', 'delete', 'ACCESS:employee', array('employee'=>'USER'));

Utils_RecordBrowserCommon::add_access('premium_checklist_item_entry', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_checklist_item_entry', 'edit', 'ACCESS:employee', array('checklist[completed]'=>''), array('checklist', 'item'));

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

Utils_RecordBrowserCommon::add_access('gc_projects', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('gc_projects', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('gc_projects', 'edit', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('gc_projects', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('gc_projects', 'delete', array('ACCESS:employee','ACCESS:manager'));

Utils_RecordBrowserCommon::add_default_access('gc_equipment');

Utils_RecordBrowserCommon::add_access('gc_projects_progbilling', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('gc_projects_progbilling', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('gc_projects_progbilling', 'edit', 'ACCESS:employee', array(), array('project_name'));
Utils_RecordBrowserCommon::add_access('gc_projects_progbilling', 'delete', 'ACCESS:employee');

Utils_RecordBrowserCommon::add_access('gc_salesgoals', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('gc_salesgoals', 'add', array('ACCESS:employee','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('gc_salesgoals', 'edit', array('ACCESS:employee','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('gc_salesgoals', 'delete', array('ACCESS:employee','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('gc_shopequipment', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('gc_shopequipment', 'add', 'ACCESS:employee', array(), array('job','units_used','total_units_used','rental_date','est_ret_date','last_history_entry'));
Utils_RecordBrowserCommon::add_access('gc_shopequipment', 'edit', 'ACCESS:employee', array(), array('job','units_used','total_units_used','rental_date','est_ret_date','last_history_entry'));
Utils_RecordBrowserCommon::add_access('gc_shopequipment', 'delete', 'ACCESS:employee');

Utils_RecordBrowserCommon::add_access('gc_shopequipment_rental', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('gc_shopequipment_rental', 'add', 'ACCESS:employee', array(), array('return_date'));
Utils_RecordBrowserCommon::add_access('gc_shopequipment_rental', 'edit', 'ACCESS:employee', array(), array('return_date', 'equipment_id'));
Utils_RecordBrowserCommon::add_access('gc_shopequipment_rental', 'delete', 'ACCESS:employee');

Utils_RecordBrowserCommon::add_access('gc_tickets', 'view', 'ACCESS:employee', array('(!permission'=>2, '|assigned_to'=>'USER', '|:Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('gc_tickets', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('gc_tickets', 'edit', 'ACCESS:employee', array('(permission'=>0, '|assigned_to'=>'USER', '|:Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('gc_tickets', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));

Utils_RecordBrowserCommon::add_access('gc_projects_visit', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('gc_projects_visit', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('gc_projects_visit', 'edit', 'ACCESS:employee');

Utils_RecordBrowserCommon::add_access('premium_listmanager', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_listmanager', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_listmanager', 'edit', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_listmanager', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('premium_listmanager', 'delete', array('ACCESS:employee','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('premium_listmanager_element', 'view', 'ACCESS:employee', array(), array('record_id'));
Utils_RecordBrowserCommon::add_access('premium_listmanager_element', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_listmanager_element', 'edit', 'ACCESS:employee', array(), array('list_name','record_id'));

Utils_RecordBrowserCommon::add_access('premium_listmanager_history', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_listmanager_history', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_listmanager_history', 'edit', 'ACCESS:employee', array(), array('target', 'list_name'));
Utils_RecordBrowserCommon::add_access('premium_listmanager_history', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('premium_listmanager_history', 'delete', array('ACCESS:employee','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('premium_logbook', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_logbook', 'add', 'ACCESS:employee', array(), array('date_and_time', 'author'));

Utils_RecordBrowserCommon::add_access('premium_multiple_addresses', 'view', 'ACCESS:employee', array(), array('record_type', 'record_id'));
Utils_RecordBrowserCommon::add_access('premium_multiple_addresses', 'add', 'ACCESS:employee', array('nickname'=>''));
Utils_RecordBrowserCommon::add_access('premium_multiple_addresses', 'edit', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_multiple_addresses', 'delete', 'ACCESS:employee');

Utils_RecordBrowserCommon::add_access('premium_payments_agent', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_payments_agent', 'add', 'ADMIN');
Utils_RecordBrowserCommon::add_access('premium_payments_agent', 'edit', 'ADMIN', array(), array('last_update', 'type'));
Utils_RecordBrowserCommon::add_access('premium_payments_agent', 'delete', 'ADMIN');

Utils_RecordBrowserCommon::add_access('premium_payments', 'view', 'ACCESS:employee', array('type'=>1), array('check_date', 'cvc_cvv','record_type','record_id', 'type'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'view', array('ACCESS:employee','ACCESS:manager'), array('type'=>1), array('check_date','record_type','record_id', 'type'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'view', 'ACCESS:employee', array('(type'=>2,'|type'=>0), array('expiration_date', 'cvc_cvv','record_type','record_id', 'type'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'view', 'ACCESS:employee', array('type'=>''), array('card_number','expiration_date','cvc_cvv','check_date','record_type','record_id', 'type'));

Utils_RecordBrowserCommon::add_access('premium_payments', 'add', 'ACCESS:employee', array('amount'=>'','type'=>1), array('record_type','record_id','status','status_description','type','check_date'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'add', 'ACCESS:employee', array('amount'=>'','(type'=>2,'|type'=>0), array('record_type','record_id','status','status_description','expiration_date','cvc_cvv','type'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'add', 'ACCESS:employee', array('amount'=>'','type'=>''), array('record_type','record_id','card_number','expiration_date','cvc_cvv','status','status_description','type','check_date'));

Utils_RecordBrowserCommon::add_access('premium_payments', 'edit', array('ACCESS:employee','ACCESS:manager'), array('<status'=>2,'type'=>1), array('record_type','record_id','status_description','type','check_date'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'edit', array('ACCESS:employee','ACCESS:manager'), array('<status'=>2,'(type'=>2,'|type'=>0), array('record_type','record_id','status_description','type','expiration_date','cvc_cvv'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'edit', array('ACCESS:employee','ACCESS:manager'), array('<status'=>2,'type'=>''), array('record_type','record_id','status_description','type','card_number','expiration_date','cvc_cvv','check_date'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'edit', 'ADMIN', array('type'=>1), array('record_type','record_id','status_description','type','check_date'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'edit', 'ADMIN', array('(type'=>2,'|type'=>0), array('record_type','record_id','status_description','type','expiration_date','cvc_cvv'));
Utils_RecordBrowserCommon::add_access('premium_payments', 'edit', 'ADMIN', array('type'=>''), array('record_type','record_id','status_description','type','card_number','expiration_date','cvc_cvv','check_date'));

Utils_RecordBrowserCommon::add_access('premium_payments', 'delete', array('ACCESS:employee','ACCESS:manager'), array('<status'=>2));
Utils_RecordBrowserCommon::add_access('premium_payments', 'delete', 'ADMIN');

Utils_RecordBrowserCommon::add_access('relations_types', 'view', 'ACCESS:employee', array(), array('type_name'));
Utils_RecordBrowserCommon::add_access('relations_types', 'add', 'ACCESS:employee', array(), array('type_name'));
Utils_RecordBrowserCommon::add_access('relations_types', 'edit', 'ACCESS:employee', array(), array('type_name'));
Utils_RecordBrowserCommon::add_access('relations_types', 'delete', 'ACCESS:employee', array('type_name'=>''));

Utils_RecordBrowserCommon::add_default_access('relations');

Utils_RecordBrowserCommon::add_access('premium_roundcube_custom_addon', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_roundcube_custom_addon', 'add', 'ADMIN');
Utils_RecordBrowserCommon::add_access('premium_roundcube_custom_addon', 'edit', 'SUPERADMIN');
Utils_RecordBrowserCommon::add_access('premium_roundcube_custom_addon', 'delete', 'SUPERADMIN');

Utils_RecordBrowserCommon::add_default_access('premium_salesopportunity');

Utils_RecordBrowserCommon::add_default_access('premium_schoolregister_course');
Utils_RecordBrowserCommon::add_default_access('premium_schoolregister_room');
Utils_RecordBrowserCommon::add_default_access('premium_schoolregister_attendance');

Utils_RecordBrowserCommon::add_access('premium_schoolregister_student_group', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_student_group', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_student_group', 'edit', 'ACCESS:employee', array(), array('students', 'course'));
Utils_RecordBrowserCommon::add_access('premium_schoolregister_student_group', 'delete', 'ACCESS:employee');

// shouldn't be done with access
Utils_RecordBrowserCommon::add_access('premium_schoolregister_schedule', 'view', 'ACCESS:employee', array('special_education'=>1, 'custom_course'=>''), array('term', 'custom_course'));
Utils_RecordBrowserCommon::add_access('premium_schoolregister_schedule', 'view', 'ACCESS:employee', array('special_education'=>'', 'custom_course'=>''), array('start_date', 'end_date', 'custom_course'));
Utils_RecordBrowserCommon::add_access('premium_schoolregister_schedule', 'view', 'ACCESS:employee', array('special_education'=>1, 'custom_course'=>1), array('term', 'course'));
Utils_RecordBrowserCommon::add_access('premium_schoolregister_schedule', 'view', 'ACCESS:employee', array('special_education'=>'', 'custom_course'=>1), array('start_date', 'end_date', 'course'));
Utils_RecordBrowserCommon::add_access('premium_schoolregister_schedule', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_schedule', 'edit', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_schedule', 'delete', 'ACCESS:employee');

// shouldn't be done with access
Utils_RecordBrowserCommon::add_access('premium_schoolregister_lesson', 'view', 'ACCESS:employee', array('custom_course'=>1), array('students', 'special_education', 'course'));
Utils_RecordBrowserCommon::add_access('premium_schoolregister_lesson', 'view', 'ACCESS:employee', array('custom_course'=>''), array('students', 'special_education', 'custom_course'));
Utils_RecordBrowserCommon::add_access('premium_schoolregister_lesson', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_lesson', 'edit', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_lesson', 'delete', 'ACCESS:employee');

Utils_RecordBrowserCommon::add_access('premium_schoolregister_att_event_type', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_att_event_type', 'add', 'SUPERADMIN');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_att_event_type', 'edit', 'SUPERADMIN');

Utils_RecordBrowserCommon::add_access('premium_schoolregister_term', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_term', 'add', 'SUPERADMIN');
Utils_RecordBrowserCommon::add_access('premium_schoolregister_term', 'edit', 'SUPERADMIN');

Utils_RecordBrowserCommon::add_access('contact_skills', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('contact_skills', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('contact_skills', 'edit', 'ACCESS:employee', array(), array('contact'));
Utils_RecordBrowserCommon::add_access('contact_skills', 'delete', 'ACCESS:employee');

Utils_RecordBrowserCommon::add_access('premium_timesheet', 'view', array('ACCESS:employee','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('premium_timesheet', 'add', array('ACCESS:employee','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('premium_timesheet', 'edit', array('ACCESS:employee','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('premium_timesheet', 'delete', array('ACCESS:employee','ACCESS:manager'));
Utils_RecordBrowserCommon::add_access('premium_timesheet', 'view', 'ACCESS:employee', array('employee'=>'USER'));
Utils_RecordBrowserCommon::add_access('premium_timesheet', 'view', 'ACCESS:employee', array(), array('billing_rate'));
Utils_RecordBrowserCommon::add_access('premium_timesheet', 'add', 'ACCESS:employee', array('employee'=>'USER'), array('employee'));
Utils_RecordBrowserCommon::add_access('premium_timesheet', 'edit', 'ACCESS:employee', array('employee'=>'USER'), array('employee'));
Utils_RecordBrowserCommon::add_access('premium_timesheet', 'delete', 'ACCESS:employee', array('employee'=>'USER'));

Utils_RecordBrowserCommon::add_default_access('premium_timesheet_rate');

Utils_RecordBrowserCommon::add_access('premium_ecommerce_products', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_ecommerce_products', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_ecommerce_products', 'edit', 'ACCESS:employee', array(), array('currency','gross_price'));
Utils_RecordBrowserCommon::add_access('premium_ecommerce_products', 'delete', 'ACCESS:employee');

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

Utils_RecordBrowserCommon::add_access('premium_warehouse_items', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_warehouse_items', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_warehouse_items', 'edit', 'ACCESS:employee', array(), array('item_type'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items', 'delete', array('ACCESS:employee', 'ACCESS:manager'));

Utils_RecordBrowserCommon::add_default_access('premium_warehouse_items_categories');

Utils_RecordBrowserCommon::add_access('premium_warehouse_location', 'view', 'ACCESS:employee');

Utils_RecordBrowserCommon::add_access('premium_warehouse', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_warehouse', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_warehouse', 'edit', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_warehouse', 'delete', array('ACCESS:employee', 'ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('premium_warehouse_distributor', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_warehouse_distributor', 'add', 'ACCESS:employee', array(), array('last_update'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_distributor', 'edit', 'ACCESS:employee', array(), array('last_update'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_distributor', 'delete', array('ACCESS:employee', 'ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('premium_warehouse_distributor', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_warehouse_distributor', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_warehouse_distributor', 'edit', 'ACCESS:employee', array(), array('last_update'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_distributor', 'delete', array('ACCESS:employee', 'ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('premium_weblink_custom_addon', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_weblink_custom_addon', 'add', 'ADMIN', array('recordset'=>''));
Utils_RecordBrowserCommon::add_access('premium_weblink_custom_addon', 'edit', 'SUPERADMIN');
Utils_RecordBrowserCommon::add_access('premium_weblink_custom_addon', 'delete', 'SUPERADMIN');

Utils_RecordBrowserCommon::add_access('premium_weblink', 'view', 'ACCESS:employee', array());
Utils_RecordBrowserCommon::add_access('premium_weblink', 'add', 'ACCESS:employee', array('link'=>''));
Utils_RecordBrowserCommon::add_access('premium_weblink', 'edit', 'ACCESS:employee', array(), array('date'));
Utils_RecordBrowserCommon::add_access('premium_weblink', 'delete', 'ACCESS:employee');

if(ModuleManager::is_installed('Premium_Weblink')>=0) {
	DB::Execute('UPDATE premium_weblink_field SET type=%s WHERE field=%s', array('hidden', 'Record Type'));
}

Utils_RecordBrowserCommon::add_access('bugtrack', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('bugtrack', 'add', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('bugtrack', 'edit', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('bugtrack', 'delete', array('ACCESS:employee', 'ACCESS:manager'));

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
	DB::Execute('UPDATE cades_behavior_log_field SET type=%s, param=%s WHERE field=%s', array('select', 'contact::Last Name|First Name;', 'Person'));
	Utils_RecordBrowserCommon::set_QFfield_callback('cades_behavior_log', 'Person', array('Custom_CADES_BehaviorCommon', 'QFfield_log_person'));

	Utils_CommonDataCommon::extend_array('Contacts/Access',array('mrm'=>_M('Medical Record Manager')));
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

	Utils_RecordBrowserCommon::new_record_field('contact', array('name' => _M('View'), 'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('Custom_CADES_AccessRestrictionsCommon', 'employee_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>false, 'extra'=>true, 'filter'=>false, 'visible'=>false));
	Utils_RecordBrowserCommon::new_record_field('contact', array('name' => _M('Edit'), 'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('Custom_CADES_AccessRestrictionsCommon', 'employee_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>false, 'extra'=>true, 'filter'=>false, 'visible'=>false));
	Utils_RecordBrowserCommon::new_record_field('contact', array('name' => _M('Add'), 'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('Custom_CADES_AccessRestrictionsCommon', 'employee_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>false, 'extra'=>true, 'filter'=>false, 'visible'=>false));
	Utils_RecordBrowserCommon::new_record_field('contact', array('name' => _M('Delete'), 'type'=>'crm_contact', 'param'=>array('field_type'=>'multiselect', 'crits'=>array('Custom_CADES_AccessRestrictionsCommon', 'employee_crits'), 'format'=>array('CRM_ContactsCommon','contact_format_no_company')), 'required'=>false, 'extra'=>true, 'filter'=>false, 'visible'=>false));

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
	Utils_RecordBrowserCommon::add_access('contact', 'view', 'ACCESS:employee', array('(!permission'=>2, '|:Created_by'=>'USER_ID'), array('birth_date','ssn','home_phone','home_address_1','home_address_2','home_city','home_country','home_zone','home_postal_code', 'view', 'edit', 'add', 'delete'));
	Utils_RecordBrowserCommon::add_access('contact', 'view', 'ALL', array('login'=>'USER_ID'), array('view', 'edit', 'add', 'delete'));
	Utils_RecordBrowserCommon::add_access('contact', 'view', array('ACCESS:employee','ACCESS:mrm'), array('(!permission'=>2, '|:Created_by'=>'USER_ID'), array('view', 'edit', 'add', 'delete'));
	Utils_RecordBrowserCommon::add_access('contact', 'add', array('ACCESS:employee','ACCESS:manager'));
	Utils_RecordBrowserCommon::add_access('contact', 'edit', 'ACCESS:employee', array('(permission'=>0, '|:Created_by'=>'USER_ID', '!group'=>array('patient', 'ex_patient')), array('access', 'login'));
	Utils_RecordBrowserCommon::add_access('contact', 'edit', 'ALL', array('login'=>'USER_ID'), array('access', 'login'));
	Utils_RecordBrowserCommon::add_access('contact', 'edit', array('ACCESS:employee','ACCESS:mrm'), array());
	Utils_RecordBrowserCommon::add_access('contact', 'delete', array('ACCESS:employee','ACCESS:mrm'));

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

	Utils_RecordBrowserCommon::add_access('cades_incidents', 'view', array('ACCESS:employee','ACCESS:mrm'));
	Utils_RecordBrowserCommon::add_access('cades_incidents', 'edit', array('ACCESS:employee','ACCESS:mrm'));
	Utils_RecordBrowserCommon::add_access('cades_incidents', 'add', array('ACCESS:employee','ACCESS:mrm'));
	Utils_RecordBrowserCommon::add_access('cades_incidents', 'delete', array('ACCESS:employee','ACCESS:mrm'));
	
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

Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders', 'view', 'ALL', array('contact'=>'USER'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders', 'view', array('ALL','ACCESS:manager'), array('company'=>'USER_COMPANY'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders', 'add', 'ACCESS:employee', array(), array('transaction_type'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders', 'edit', 'ACCESS:employee', array('employee'=>'USER', '(>=transaction_date'=>'-1 week', '|<status'=>20), array('transaction_type', 'warehouse'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders', 'edit', 'ACCESS:employee', array('employee'=>'USER', 'warehouse'=>''), array('transaction_type'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders', 'edit', array('ACCESS:employee','ACCESS:manager'), array(), array('transaction_type', 'warehouse'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders', 'delete', array('ACCESS:employee','ACCESS:manager'));

Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders_details', 'view', 'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders_details', 'view', 'ALL', array('transaction_id[contact]'=>'USER'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders_details', 'view', array('ALL','ACCESS:manager'), array('transaction_id[company]'=>'USER_COMPANY'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders_details', 'add', 'ACCESS:employee', array('transaction_id[employee]'=>'USER', '(>=transaction_id[transaction_date]'=>'-1 week', '|<transaction_id[status]'=>20), array('transaction_id'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders_details', 'add', array('ACCESS:employee','ACCESS:manager'), array(), array('transaction_id'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders_details', 'edit', 'ACCESS:employee', array('transaction_id[employee]'=>'USER', '(>=transaction_id[transaction_date]'=>'-1 week', '|<transaction_id[status]'=>20), array('transaction_id'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders_details', 'edit', array('ACCESS:employee','ACCESS:manager'), array(), array('transaction_id'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders_details', 'delete', 'ACCESS:employee', array(':Created_by'=>'USER_ID'));
Utils_RecordBrowserCommon::add_access('premium_warehouse_items_orders_details', 'delete', array('ACCESS:employee','ACCESS:manager'));


?>