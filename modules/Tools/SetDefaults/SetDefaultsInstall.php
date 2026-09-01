<?php
/**
 * @author Janusz Tylek <jtylek@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @version 1.0
 * @license MIT
 * @package epesi-tools
 * @subpackage setdefaults
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tools_SetDefaultsInstall extends ModuleInstall {

	public function install() {

        // The curated Quick Access/Launchpad "keep enabled" defaults used to
        // be applied right here, but that only works AFTER
        // Base_Menu_QuickAccessCommon::freeze_current_items_as_grandfathered()
        // has run - see Tools_SetDefaultsCommon::apply_quickaccess_defaults()
        // for why, and FirstRun_0.php::done() for where it's actually called
        // now (right after that freeze call).

		// default applets
		DB::Execute('INSERT INTO base_dashboard_default_applets (id, module_name, col, pos, color, tab) VALUES (%d, %s, %d, %d, %d, %d)', array(1,'Applets_Clock',2,0,1,1));
		DB::Execute('INSERT INTO base_dashboard_default_applets (id, module_name, col, pos, color, tab) VALUES (%d, %s, %d, %d, %d, %d)', array(2,'CRM_Tasks',1,0,6,1));
		DB::Execute('INSERT INTO base_dashboard_default_applets (id, module_name, col, pos, color, tab) VALUES (%d, %s, %d, %d, %d, %d)', array(3,'CRM_PhoneCall',1,1,8,1));
		DB::Execute('INSERT INTO base_dashboard_default_applets (id, module_name, col, pos, color, tab) VALUES (%d, %s, %d, %d, %d, %d)', array(4,'Applets_Note',2,0,10,1));
		DB::Execute('INSERT INTO base_dashboard_default_applets (id, module_name, col, pos, color, tab) VALUES (%d, %s, %d, %d, %d, %d)', array(5,'CRM_Calendar',1,2,0,1));
		DB::Execute('INSERT INTO base_dashboard_default_applets (id, module_name, col, pos, color, tab) VALUES (%d, %s, %d, %d, %d, %d)', array(6,'Apps_Shoutbox',0,1,0,1));
		DB::Execute('INSERT INTO base_dashboard_default_applets (id, module_name, col, pos, color, tab) VALUES (%d, %s, %d, %d, %d, %d)', array(7,'Utils_Watchdog',0,0,6,1));


		//default note
		DB::Execute('INSERT INTO base_dashboard_default_settings (applet_id,name,value) VALUES (%d, %s, %s)', array(4,'bcolor','nice-yellow'));
		DB::Execute('INSERT INTO base_dashboard_default_settings (applet_id,name,value) VALUES (%d, %s, %s)',  array(4,'text','<div><strong>'.__('Congratulations!').'</strong><br />'.__('You\'ve just installed Epesi!').'</div><div>'.__('For more information, help and support please visit %sEpesi website %s', array('<a href="https://epesi.org" target="_blank">','</a></div>'))));
		DB::Execute('INSERT INTO base_dashboard_default_settings (applet_id,name,value) VALUES (%d, %s, %s)', array(4,'title',__('Welcome')));

		// default favorites and subscriptions
        Base_User_SettingsCommon::save_admin('Utils_RecordBrowser', 'company_auto_fav', '1');
        Base_User_SettingsCommon::save_admin('Utils_RecordBrowser', 'company_auto_subs', '1');
        Base_User_SettingsCommon::save_admin('Utils_RecordBrowser', 'contact_auto_fav', '1');
        Base_User_SettingsCommon::save_admin('Utils_RecordBrowser', 'contact_auto_subs', '1');
        Base_User_SettingsCommon::save_admin('Utils_RecordBrowser', 'phonecall_auto_subs', '1');
        Base_User_SettingsCommon::save_admin('Utils_RecordBrowser', 'task_auto_subs', '1');

		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array("2.0");
	}
	
	public function requires($v) {
		return array(
			array('name'=>Base_ThemeInstall::module_name(),'version'=>0),
			array('name'=>Base_LangInstall::module_name(),'version'=>0),
			array('name'=>Utils_WizardInstall::module_name(),'version'=>0),
			array('name'=>CRM_CalendarInstall::module_name(),'version'=>0),
			array('name'=>CRM_ContactsInstall::module_name(),'version'=>0),
			array('name'=>CRM_PhoneCallInstall::module_name(),'version'=>0),
			);
	}
}

?>