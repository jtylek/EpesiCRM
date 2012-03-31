<?php

if (ModuleManager::is_installed('CRM_Contacts')!=-1) {
	Utils_RecordBrowserCommon::set_tpl('company', '');
}

if (ModuleManager::is_installed('Premium_SalesOpportunity')!=-1) {
	Utils_RecordBrowserCommon::set_tpl('premium_salesopportunity', '');
}

?>