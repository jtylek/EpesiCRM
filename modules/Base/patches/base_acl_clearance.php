<?php
if (ModuleManager::is_installed('Base_Acl')==-1) return;

@DB::DropTable('base_acl_clearance');
DB::CreateTable('base_acl_clearance',
	'id I4 AUTO KEY,'.
	'callback C(128)',
	array('constraints' => ''));
DB::Execute('INSERT INTO base_acl_clearance (callback) VALUES (%s)', array('Base_AclCommon::basic_clearance'));

if (ModuleManager::is_installed('CRM_Contacts')==-1) return;
DB::Execute('INSERT INTO base_acl_clearance (callback) VALUES (%s)', array('CRM_ContactsCommon::crm_clearance'));

?>
