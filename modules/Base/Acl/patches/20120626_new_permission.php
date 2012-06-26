<?php

DB::CreateTable('base_acl_permission',
	'id I4 AUTO KEY,'.
	'name C(255)',
	array('constraints' => ''));
DB::CreateTable('base_acl_rules',
	'id I4 AUTO KEY,'.
	'permission_id I',
	array('constraints' => ', FOREIGN KEY (permission_id) REFERENCES base_acl_permission(id)'));
DB::CreateTable('base_acl_rules_clearance',
	'id I4 AUTO KEY,'.
	'rule_id I,'.
	'clearance C(64)',
	array('constraints' => ', FOREIGN KEY (rule_id) REFERENCES base_acl_rules(id)'));

$permissions = array(
	'Apps_ActivityReport'=>array('View Activity Report',array('ACCESS:employee','ACCESS:manager')),
	'Apps_Shoutbox'=>array('Shoutbox',array('ACCESS:employee')), 
	'Base_Dashboard'=>array('Dashboard',array('ACCESS:employee')), 
	'Base_Search'=>array('Search',array('ACCESS:employee')), 
	'Base_User_Settings'=>array('Advanced User Settings',array('ACCESS:employee')), 
	'CRM_Calendar'=>array('Calendar',array('ACCESS:employee')), 
	'CRM_Filters'=>array('Manage Perspective',array('ACCESS:employee')), 
	'Premium_Import'=>array('Import',array('SUPERADMIN')), 
	'Premium_Warehouse_Items_Orders'=>array('Inventory - Sell at loss',array('ACCESS:employee','ACCESS:manager')), 
	'Utils_Attachment'=>array('Attachments - view full download history', array('ACCESS:employee')), 
	'Utils_Messenger'=>array('Messenger Alerts',array('ACCESS:employee')), 
	'CRM_Fax'=>array('Fax - Browse',array('ACCESS:employee')), 
	'CRM_Fax'=>array('Fax - Send',array('ACCESS:employee')), 
	'Premium_Warehouse_eCommerce_Allegro'=>array('Inventory - Allegro Settings',array('ACCESS:employee'))
);

foreach ($permissions as $module=>$params) {
	if (ModuleManager::is_installed($module)>=0)
		Base_AclCommon::add_permission($params[0], $params[1]);
}

?>
